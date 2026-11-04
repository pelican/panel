<?php

namespace App\Services\Servers;

use App\Enums\ContainerStatus;
use App\Enums\ServerTransferBatchItemStatus as ItemStatus;
use App\Enums\ServerTransferBatchStatus as BatchStatus;
use App\Exceptions\Service\Server\TransferBatchException;
use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Extensions\BackupAdapter\Schemas\WingsBackupSchema;
use App\Jobs\AdvanceServerTransferBatch;
use App\Jobs\SendServerPowerAction;
use App\Models\Allocation;
use App\Models\Backup;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerTransferBatch;
use App\Models\ServerTransferBatchItem;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Repositories\Daemon\DaemonSystemRepository;
use Filament\Notifications\Notification;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * Moves many servers between two nodes by queueing them on the panel. Wings only knows
 * about the single transfer in progress; the batch starts the next one when it reports back.
 */
class ServerTransferBatchService
{
    /** Wings fails a transfer if the server takes over 15 seconds to stop, so it is stopped beforehand. */
    private const int STOP_TIMEOUT_SECONDS = 300;

    /** Wings has to finish unlocking the server after reporting to the panel before it accepts power actions. */
    private const int START_DELAY_SECONDS = 15;

    private const int CONFLICTS_IN_MESSAGE = 5;

    private const string IPV4_WILDCARD = '0.0.0.0';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly TransferServerService $transferService,
        private readonly DaemonServerRepository $daemonRepository,
        private readonly DaemonSystemRepository $systemRepository,
        private readonly BackupAdapterService $backupService,
    ) {}

    /**
     * The IPs a server can be given on a node: what the daemon reports plus what allocations already use.
     *
     * @return string[]
     */
    public function availableIps(Node $node): array
    {
        return collect($node->ipAddresses())
            ->concat($node->allocations()->distinct()->pluck('ip'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The backups stored on the node itself. Those on remote storage like S3 aren't affected by a transfer.
     *
     * @return EloquentCollection<int, Backup>
     */
    public function localBackups(Server $server): EloquentCollection
    {
        return $server->backups->filter(fn (Backup $backup) => $this->backupService->get($backup->backupHost->schema) instanceof WingsBackupSchema);
    }

    /**
     * @param  array<int, array{ip: string|null, move_backups: bool}>  $assignments  what happens to each server, keyed by server id
     *
     * @throws TransferBatchException
     * @throws Throwable
     */
    public function create(Node $source, Node $target, array $assignments, ?User $user = null): ServerTransferBatch
    {
        $servers = $source->servers()->whereKey(array_keys($assignments))->with('allocations')->get();

        throw_if($source->is($target), new TransferBatchException(trans('admin/node.transfer_batch.errors.same_node')));
        throw_if($servers->isEmpty(), new TransferBatchException(trans('admin/node.transfer_batch.errors.no_servers')));

        $batch = $this->connection->transaction(function () use ($source, $target, $servers, $assignments, $user) {
            // Two batches sharing a node could both pass the checks below and would compete for its bandwidth.
            Node::query()->whereKey([$source->id, $target->id])->lockForUpdate()->get();

            $nodeIds = [$source->id, $target->id];
            $nodeIsBusy = ServerTransferBatch::query()
                ->where('status', BatchStatus::Running)
                ->where(fn ($query) => $query->whereIn('old_node', $nodeIds)->orWhereIn('new_node', $nodeIds))
                ->exists();
            throw_if($nodeIsBusy, new TransferBatchException(trans('admin/node.transfer_batch.errors.node_busy')));

            throw_unless(
                $target->isViable((int) $servers->sum('memory'), (int) $servers->sum('disk'), (int) $servers->sum('cpu')),
                new TransferBatchException(trans('admin/node.transfer_batch.errors.no_capacity', ['node' => $target->name]))
            );

            $this->validateAssignments($target, $servers, $assignments);

            $batch = ServerTransferBatch::create([
                'old_node' => $source->id,
                'new_node' => $target->id,
                'user_id' => $user?->id,
                'status' => BatchStatus::Running,
            ]);

            $batch->items()->createMany($servers->map(fn (Server $server) => [
                'server_id' => $server->id,
                'new_ip' => $server->allocation_id ? $assignments[$server->id]['ip'] : null,
                'move_backups' => $assignments[$server->id]['move_backups'],
                'status' => ItemStatus::Queued,
            ])->all());

            return $batch;
        });

        AdvanceServerTransferBatch::dispatch($batch);

        return $batch;
    }

    /**
     * Every server needs a real IP, and its ports must be free on that IP, also among the servers moving with it.
     *
     * @param  EloquentCollection<int, Server>  $servers
     * @param  array<int, array{ip: string|null, move_backups: bool}>  $assignments
     *
     * @throws TransferBatchException
     */
    private function validateAssignments(Node $target, EloquentCollection $servers, array $assignments): void
    {
        $availableIps = $this->availableIps($target);
        $ips = [];

        foreach ($servers as $server) {
            $ips[$server->id] = $assignments[$server->id]['ip'] ?? null;

            if ($server->allocation_id) {
                throw_unless(in_array($ips[$server->id], $availableIps, true), new TransferBatchException(trans('admin/node.transfer_batch.errors.select_ip', ['server' => $server->name])));
            }
        }

        $conflicts = $this->portConflicts($target, $servers, $ips);

        if ($conflicts) {
            $names = $servers->whereIn('id', array_keys($conflicts))->take(self::CONFLICTS_IN_MESSAGE)
                ->map(fn (Server $server) => "$server->name (" . implode(', ', $conflicts[$server->id]) . ')');

            $more = count($conflicts) - $names->count();
            if ($more > 0) {
                $names->push(trans_choice('admin/node.transfer_batch.errors.more_conflicts', $more));
            }

            throw new TransferBatchException(trans('admin/node.transfer_batch.errors.ports_taken', ['conflicts' => $names->implode(', ')]));
        }
    }

    /**
     * The addresses that are in use on the target, by a server already there or an earlier one in the list.
     *
     * @param  EloquentCollection<int, Server>  $servers
     * @param  array<int, string|null>  $ips  the IP chosen for each server, by server id
     * @return array<int, string[]> the addresses of each server that has a conflict, by server id
     */
    public function portConflicts(Node $target, EloquentCollection $servers, array $ips): array
    {
        $taken = $this->takenPorts($target->id);
        $conflicts = [];

        foreach ($servers as $server) {
            $ip = $ips[$server->id] ?? null;

            if (!$server->allocation_id || !$ip) {
                continue;
            }

            foreach ($this->portsOf($server) as $port) {
                if ($this->isPortTaken($taken[$port] ?? [], $ip)) {
                    $conflicts[$server->id][] = "$ip:$port";
                }

                $taken[$port][$ip] = true;
            }
        }

        return $conflicts;
    }

    /**
     * @return array<int, array<string, true>> the IPs that are in use on a node, by port
     */
    public function takenPorts(int $nodeId): array
    {
        $taken = [];

        foreach (Allocation::query()->where('node_id', $nodeId)->whereNotNull('server_id')->get() as $allocation) {
            $taken[$allocation->port][$allocation->ip] = true;
        }

        return $taken;
    }

    /**
     * Listening on 0.0.0.0 occupies the port on every IPv4 address, so those two can't share a port either way around.
     * IPv6 is compared as is: whether :: clashes depends on how the node publishes ports.
     *
     * @param  array<string, true>  $ipsInUse  the IPs in use on a port
     */
    public function isPortTaken(array $ipsInUse, string $ip): bool
    {
        if (isset($ipsInUse[$ip])) {
            return true;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if ($ip !== self::IPV4_WILDCARD) {
            return isset($ipsInUse[self::IPV4_WILDCARD]);
        }

        return collect($ipsInUse)->keys()->contains(fn (string $used) => filter_var($used, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
    }

    /** @return int[] */
    private function portsOf(Server $server): array
    {
        return $server->allocations->where('node_id', $server->node_id)->pluck('port')->all();
    }

    /**
     * Moves the batch one step forward.
     *
     * @return bool whether the batch should be advanced again shortly, as opposed to
     *              waiting for a callback from the daemon or the scheduled wake-up
     */
    public function advance(ServerTransferBatch $batch): bool
    {
        if ($batch->refresh()->status !== BatchStatus::Running) {
            return false;
        }

        $item = $batch->activeItem()->first()
            ?? $batch->items()->where('status', ItemStatus::Queued)->orderBy('id')->first();

        if (!$item) {
            $this->finish($batch, BatchStatus::Completed);

            return false;
        }

        return $item->status === ItemStatus::Transferring ? $this->checkTransfer($item) : $this->prepareServer($item);
    }

    /**
     * Stops the batch, aborting the transfer it is currently waiting on.
     */
    public function cancel(ServerTransferBatch $batch): void
    {
        if ($batch->status !== BatchStatus::Running) {
            return;
        }

        // By id: settling takes an item out of these queries, and paging by offset would then skip others.
        $batch->items()->whereIn('status', [ItemStatus::Stopping, ItemStatus::Transferring])->eachById(
            fn (ServerTransferBatchItem $item) => $this->abort($item, ItemStatus::Cancelled, null)
        );

        $batch->items()->where('status', ItemStatus::Queued)->eachById(
            fn (ServerTransferBatchItem $item) => $this->settle($item, ItemStatus::Cancelled, null)
        );

        $this->finish($batch, BatchStatus::Cancelled);
    }

    private function checkTransfer(ServerTransferBatchItem $item): bool
    {
        $transfer = $item->transfer;

        if ($transfer && $transfer->successful === null) {
            $timeout = (int) config('panel.transfers.batch_timeout');
            if ($timeout <= 0 || $item->started_at?->addMinutes($timeout)->isFuture()) {
                return false;
            }

            $this->abort($item, ItemStatus::Failed, trans('admin/node.transfer_batch.errors.timed_out', ['minutes' => $timeout]));

            return true;
        }

        // The application API "cancels" a transfer by marking it successful, so check that the server really moved.
        if ($transfer?->successful && $item->server->node_id === $transfer->new_node) {
            // The files went away with the server on the old node. Only now, so a failed transfer loses nothing.
            // Backups newer than the transfer were made on the new node, by which time the server may be in use again.
            if (!$item->move_backups) {
                $this->localBackups($item->server)
                    ->filter(fn (Backup $backup) => $backup->created_at->lte($transfer->created_at))
                    ->each(fn (Backup $backup) => $backup->delete());
            }

            $this->settle($item, ItemStatus::Successful, null);
        } else {
            $this->settle($item, ItemStatus::Failed, trans('admin/node.transfer_batch.errors.transfer_failed'));
        }

        return true;
    }

    private function prepareServer(ServerTransferBatchItem $item): bool
    {
        $server = $item->server;

        if ($server->node_id !== $item->batch->old_node) {
            $this->settle($item, ItemStatus::Failed, trans('admin/node.transfer_batch.errors.server_moved'));

            return true;
        }

        try {
            $server->validateTransferState();
        } catch (Throwable $exception) {
            $this->settle($item, ItemStatus::Failed, $exception->getMessage());

            return true;
        }

        // A transfer to a node that is down can only fail, after taking the server offline for nothing.
        if ($item->status === ItemStatus::Queued && !$this->targetIsReachable($item->batch)) {
            $this->waitForTarget($item);

            return false;
        }

        $state = $this->daemonRepository->setServer($server)->getState(5);

        if ($state?->isOffline()) {
            return $this->startTransfer($item);
        }

        if (!$item->stop_requested_at) {
            // It may well be running, and would then not be started again after moving. The scheduled wake-up tries again.
            if ($state === null) {
                $item->update(['error' => trans('admin/node.transfer_batch.errors.state_unknown')]);

                return false;
            }

            return $this->stopServer($item, $state);
        }

        if ($item->stop_requested_at->addSeconds(self::STOP_TIMEOUT_SECONDS)->isPast()) {
            $this->settle($item, ItemStatus::Failed, trans('admin/node.transfer_batch.errors.stop_timeout'));
        }

        return true;
    }

    private function targetIsReachable(ServerTransferBatch $batch): bool
    {
        try {
            $this->systemRepository->setNode($batch->newNode)->getSystemInformation();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Leaves the server running and the batch where it is. The scheduled wake-up tries again.
     */
    private function waitForTarget(ServerTransferBatchItem $item): void
    {
        $batch = $item->batch;
        $message = trans('admin/node.transfer_batch.errors.target_unreachable', ['node' => $batch->newNode->name]);

        if ($item->error === $message) {
            return;
        }

        $item->update(['error' => $message]);

        if ($batch->user) {
            Notification::make()
                ->title(trans('admin/node.transfer_batch.notifications.paused', ['node' => $batch->oldNode->name]))
                ->body($message)
                ->warning()
                ->sendToDatabase($batch->user);
        }
    }

    private function stopServer(ServerTransferBatchItem $item, ContainerStatus $state): bool
    {
        // Kept once set: the stop may have worked even if the answer got lost, and the next look at the server would then find it offline.
        $wasRunning = $item->was_running || $state->isStartingOrRunning();

        try {
            $this->daemonRepository->setServer($item->server)->power('stop');
        } catch (ConnectionException $exception) {
            $item->update(['error' => $exception->getMessage(), 'was_running' => $wasRunning]);

            return false;
        } catch (Throwable $exception) {
            $this->settle($item, ItemStatus::Failed, $exception->getMessage());

            return true;
        }

        $item->update([
            'status' => ItemStatus::Stopping,
            'stop_requested_at' => now(),
            'was_running' => $wasRunning,
            'error' => null,
        ]);

        return true;
    }

    private function startTransfer(ServerTransferBatchItem $item): bool
    {
        $server = $item->server;

        $allocations = $this->resolveAllocations($server, $item->batch->new_node, $item->new_ip);
        if ($allocations === null) {
            $this->settle($item, ItemStatus::Failed, trans('admin/node.transfer_batch.errors.port_taken', ['ip' => $item->new_ip]));

            return true;
        }

        try {
            $transfer = $this->transferService->handle(
                $server,
                $item->batch->new_node,
                $allocations['primary'],
                $allocations['additional'],
                $item->move_backups ? $this->localBackups($server)->pluck('uuid')->all() : [],
            );
        } catch (ConnectionException $exception) {
            // The node may just be restarting: keep the server queued and try again on the next scheduled wake-up.
            $item->update(['status' => ItemStatus::Queued, 'error' => $exception->getMessage()]);

            return false;
        } catch (Throwable $exception) {
            $this->settle($item, ItemStatus::Failed, $exception->getMessage());

            return true;
        }

        $item->update([
            'status' => ItemStatus::Transferring,
            'server_transfer_id' => $transfer->id,
            'started_at' => now(),
            'error' => null,
        ]);

        return false;
    }

    /**
     * Gets the server's ports on the chosen IP of the new node, creating those that don't exist yet.
     *
     * @return array{primary: int|null, additional: int[]}|null null when one of the ports is in use by now
     */
    private function resolveAllocations(Server $server, int $nodeId, ?string $ip): ?array
    {
        if (!$server->allocation_id || $ip === null) {
            return ['primary' => null, 'additional' => []];
        }

        $primaryPort = $server->allocation->port;
        $taken = $this->takenPorts($nodeId);
        $additional = [];
        $primary = null;

        foreach ($this->portsOf($server) as $port) {
            if ($this->isPortTaken($taken[$port] ?? [], $ip)) {
                return null;
            }

            $allocation = Allocation::query()->firstOrCreate(['node_id' => $nodeId, 'ip' => $ip, 'port' => $port]);

            if ($port === $primaryPort) {
                $primary = $allocation->id;
            } else {
                $additional[] = $allocation->id;
            }
        }

        return ['primary' => $primary, 'additional' => $additional];
    }

    private function abort(ServerTransferBatchItem $item, ItemStatus $status, ?string $error): void
    {
        $transfer = $item->transfer;

        // Too late to abort: it finished, and only the batch hadn't caught up yet.
        if ($transfer?->successful && $item->server->node_id === $transfer->new_node) {
            $this->checkTransfer($item);

            return;
        }

        if ($transfer && $transfer->successful === null) {
            try {
                $this->daemonRepository->setServer($item->server)->cancelTransfer();
            } catch (Throwable $exception) {
                // A node that cannot be reached is usually why we are aborting in the first place.
                logger()->warning($exception, ['transfer_id' => $transfer->id]);
            }

            $transfer->markFailed();
        }

        $this->settle($item, $status, $error);
    }

    private function settle(ServerTransferBatchItem $item, ItemStatus $status, ?string $error): void
    {
        $item->update(['status' => $status, 'error' => $error, 'finished_at' => now()]);

        if ($item->was_running) {
            SendServerPowerAction::dispatch($item->server, 'start')->delay(now()->addSeconds(self::START_DELAY_SECONDS));
        }
    }

    private function finish(ServerTransferBatch $batch, BatchStatus $status): void
    {
        $batch->update(['status' => $status, 'finished_at' => now()]);

        if (!$batch->user) {
            return;
        }

        $failed = $batch->failedItems()->count();

        $notification = Notification::make()
            ->title(trans('admin/node.transfer_batch.notifications.finished', ['node' => $batch->oldNode->name]))
            ->body(trans('admin/node.transfer_batch.notifications.summary', [
                'successful' => $batch->items()->where('status', ItemStatus::Successful)->count(),
                'failed' => $failed,
                'cancelled' => $batch->items()->where('status', ItemStatus::Cancelled)->count(),
            ]));

        ($failed > 0 ? $notification->danger() : $notification->success())->sendToDatabase($batch->user);
    }
}
