<?php

namespace App\Services\Servers;

use App\Enums\NodeJwtScope;
use App\Exceptions\Http\Server\NodeNotViableException;
use App\Exceptions\Service\Allocation\AllocationUnavailableException;
use App\Exceptions\Service\Server\TransferUnconfirmedException;
use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Extensions\BackupAdapter\Schemas\WingsBackupSchema;
use App\Models\Allocation;
use App\Models\Backup;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerTransfer;
use App\Services\Nodes\NodeJWTService;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\UnencryptedToken;
use Throwable;

class TransferServerService
{
    /**
     * TransferService constructor.
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly NodeJWTService $nodeJWTService,
        private readonly BackupAdapterService $backupService
    ) {}

    private function notify(ServerTransfer $transfer, UnencryptedToken $token): void
    {
        // Make sure only wings backups of the current server are forwarded in the wings request
        $backups = Backup::where('server_id', $transfer->server_id)
            ->whereIn('uuid', $transfer->backup_uuids ?? [])
            ->with('backupHost')
            ->get()
            ->filter(fn (Backup $backup) => $this->backupService->get($backup->backupHost->schema) instanceof WingsBackupSchema)
            ->pluck('uuid')
            ->all();

        // Wings waits up to 15 seconds for a running server to stop before it responds
        Http::daemon($transfer->oldNode)->timeout(30)->post("/api/servers/{$transfer->server->uuid}/transfer", [
            'url' => $transfer->newNode->getConnectionAddress() . '/api/transfers',
            'token' => 'Bearer ' . $token->toString(),
            'backups' => $backups,
            'server' => [
                'uuid' => $transfer->server->uuid,
                'start_on_completion' => false,
            ],
        ])->throw();
    }

    /**
     * Starts a transfer of a server to a new node.
     *
     * @param  int[]  $additional_allocations
     * @param  string[]  $backup_uuids
     *
     * @throws NodeNotViableException
     * @throws Throwable
     */
    public function handle(Server $server, int $node_id, ?int $allocation_id = null, ?array $additional_allocations = [], array $backup_uuids = []): ServerTransfer
    {
        $additional_allocations = array_map(intval(...), $additional_allocations);

        // Check if the node is viable for the transfer.
        $node = Node::query()
            ->select(['nodes.id', 'nodes.fqdn', 'nodes.scheme', 'nodes.daemon_token', 'nodes.daemon_connect', 'nodes.memory', 'nodes.disk', 'nodes.cpu', 'nodes.memory_overallocate', 'nodes.disk_overallocate', 'nodes.cpu_overallocate'])
            ->withSum('servers', 'disk')
            ->withSum('servers', 'memory')
            ->withSum('servers', 'cpu')
            ->leftJoin('servers', 'servers.node_id', '=', 'nodes.id')
            ->where('nodes.id', $node_id)
            ->first();

        if (!$node->isViable($server->memory, $server->disk, $server->cpu)) {
            throw new NodeNotViableException($node);
        }

        $server->validateTransferState();

        /** @var ServerTransfer $transfer */
        $transfer = $this->connection->transaction(function () use ($server, $node_id, $allocation_id, $additional_allocations, $backup_uuids) {
            // Create a new ServerTransfer entry.
            $transfer = ServerTransfer::create([
                'server_id' => $server->id,
                'old_node' => $server->node_id,
                'new_node' => $node_id,
                'backup_uuids' => $backup_uuids,
            ]);

            if ($server->allocation_id) {
                $transfer->old_allocation = $server->allocation_id;
                $transfer->new_allocation = $allocation_id;
                $transfer->old_additional_allocations = $server->allocations->where('id', '!=', $server->allocation_id)->pluck('id')->all();
                $transfer->new_additional_allocations = $additional_allocations;

                // Add the allocations to the server, so they cannot be automatically assigned while the transfer is in progress.
                $this->assignAllocationsToServer($server, $node_id, $allocation_id, $additional_allocations);
            }

            $transfer->save();

            return $transfer;
        });

        try {
            // Generate a token for the destination node that the source node can use to authenticate with.
            $token = $this->nodeJWTService
                ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
                ->setScopes(NodeJwtScope::ServerTransfer)
                ->setSubject($server->uuid)
                ->handle($transfer->newNode, $server->uuid);

            // Notify the source node of the pending outgoing transfer.
            $this->notify($transfer, $token);
        } catch (Throwable $exception) {
            // Marking it failed would make the panel refuse the result if the daemon is in fact running it.
            if ($this->mayHaveReachedDaemon($exception)) {
                throw new TransferUnconfirmedException($transfer, $exception);
            }

            // The daemon won't report on a transfer it never accepted, which would leave the server locked forever.
            $transfer->markFailed();

            throw $exception;
        }

        return $transfer;
    }

    /**
     * Whether a request that failed could still have been acted on: the connection worked but no full
     * answer came back, as opposed to the daemon having been refused, unresolvable or out of reach.
     */
    private function mayHaveReachedDaemon(Throwable $exception): bool
    {
        $cause = $exception instanceof ConnectionException ? $exception->getPrevious() : null;

        return $cause instanceof TransferException && !$cause instanceof ConnectException;
    }

    /**
     * Assigns the specified allocations to the specified server.
     *
     * @param  int[]  $additional_allocations
     */
    private function assignAllocationsToServer(Server $server, int $node_id, int $allocation_id, array $additional_allocations): void
    {
        $allocations = array_unique([...$additional_allocations, $allocation_id]);

        $node = Node::findOrFail($node_id);

        // Only claims what is still free, so another server's allocation can't end up on this server too.
        $node->allocations()
            ->whereIn('id', $allocations)
            ->whereNull('server_id')
            ->update(['server_id' => $server->id]);

        $claimed = $node->allocations()
            ->whereIn('id', $allocations)
            ->where('server_id', $server->id)
            ->count();

        throw_if($claimed !== count($allocations), new AllocationUnavailableException());
    }
}
