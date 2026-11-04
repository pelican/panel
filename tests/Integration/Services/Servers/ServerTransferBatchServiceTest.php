<?php

namespace App\Tests\Integration\Services\Servers;

use App\Enums\ServerTransferBatchItemStatus as ItemStatus;
use App\Enums\ServerTransferBatchStatus as BatchStatus;
use App\Exceptions\Service\HasActiveServersException;
use App\Exceptions\Service\Server\TransferBatchException;
use App\Jobs\AdvanceServerTransferBatch;
use App\Jobs\SendServerPowerAction;
use App\Models\Allocation;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerTransfer;
use App\Models\ServerTransferBatch;
use App\Services\Servers\ServerTransferBatchService;
use App\Tests\Integration\IntegrationTestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;

class ServerTransferBatchServiceTest extends IntegrationTestCase
{
    private const string IP = '10.0.0.5';

    private Node $source;

    private Node $target;

    private string $daemonState = 'offline';

    private bool $targetIsUp = true;

    private bool $sourceAnswersPower = true;

    private bool $sourceAnswersState = true;

    private ?ConnectionException $transferFailure = null;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([AdvanceServerTransferBatch::class, SendServerPowerAction::class]);

        $this->source = Node::factory()->create();
        $this->target = Node::factory()->create();

        // Makes the IP available on the target
        Allocation::factory()->create(['node_id' => $this->target->id, 'ip' => self::IP, 'port' => 1]);
    }

    /** @param Server[] $servers */
    private function batch(array $servers, string $ip = self::IP, bool $moveBackups = true): ServerTransferBatch
    {
        return $this->service()->create(
            $this->source,
            $this->target,
            collect($servers)->mapWithKeys(fn (Server $server) => [$server->id => ['ip' => $ip, 'move_backups' => $moveBackups]])->all()
        );
    }

    /** @return array{local: Backup, remote: Backup} */
    private function backupsOf(Server $server): array
    {
        $localHost = BackupHost::factory()->create(['schema' => 'wings']);
        $remoteHost = BackupHost::factory()->create(['schema' => 's3', 'configuration' => ['bucket' => 'test']]);

        return [
            'local' => Backup::factory()->create(['server_id' => $server->id, 'backup_host_id' => $localHost->id]),
            'remote' => Backup::factory()->create(['server_id' => $server->id, 'backup_host_id' => $remoteHost->id]),
        ];
    }

    private function serverOnPort(int $port): Server
    {
        $allocation = Allocation::factory()->create(['node_id' => $this->source->id, 'port' => $port]);
        $server = $this->createServerModel(['node_id' => $this->source->id, 'allocation_id' => $allocation->id]);
        $allocation->update(['server_id' => $server->id]);

        return $server->refresh();
    }

    private function fakeDaemon(string $state = 'offline'): void
    {
        $this->daemonState = $state;

        Http::fake([
            '*/transfer' => fn () => $this->transferFailure ? throw $this->transferFailure : Http::response(status: 202),
            '*/api/transfers/*' => Http::response(status: 204),
            '*/power' => fn () => $this->sourceAnswersPower ? Http::response(status: 204) : throw new ConnectionException('lost'),
            '*/api/servers/*' => fn () => $this->sourceAnswersState ? Http::response(['state' => $this->daemonState]) : throw new ConnectionException('timed out'),
            '*/api/system' => fn () => $this->targetIsUp ? $this->systemInformation() : throw new ConnectionException('down'),
        ]);
    }

    private function systemInformation(): PromiseInterface
    {
        return Http::response(['architecture' => 'amd64', 'cpu_count' => 1, 'kernel_version' => '1', 'os' => 'linux', 'version' => '1'], headers: ['User-Agent' => 'Pelican Wings']);
    }

    private function service(): ServerTransferBatchService
    {
        return $this->app->make(ServerTransferBatchService::class);
    }

    public function test_only_one_server_transfers_at_a_time(): void
    {
        $this->fakeDaemon();
        $first = $this->serverOnPort(25565);
        $second = $this->serverOnPort(25566);
        $batch = $this->batch([$first, $second]);

        Bus::assertDispatched(AdvanceServerTransferBatch::class);

        $this->assertFalse($this->service()->advance($batch));
        $this->assertFalse($this->service()->advance($batch));

        $this->assertSame(1, ServerTransfer::query()->count());
        $this->assertSame(ItemStatus::Queued, $batch->items()->where('server_id', $second->id)->first()->status);

        // Finishing the first one lets the batch continue with the second.
        $this->transferSucceeds($first);
        $this->assertTrue($this->service()->advance($batch));
        $this->assertFalse($this->service()->advance($batch));

        $this->assertSame(ItemStatus::Successful, $batch->items()->where('server_id', $first->id)->first()->status);
        $this->assertSame(ItemStatus::Transferring, $batch->items()->where('server_id', $second->id)->first()->status);

        $this->transferSucceeds($second);
        $this->service()->advance($batch);
        $this->service()->advance($batch);

        $this->assertSame(BatchStatus::Completed, $batch->refresh()->status);
    }

    public function test_ports_are_kept_on_the_chosen_ip_and_missing_ones_created(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        Allocation::factory()->create(['node_id' => $this->source->id, 'port' => 25566, 'server_id' => $server->id]);
        $existing = Allocation::factory()->create(['node_id' => $this->target->id, 'ip' => self::IP, 'port' => 25566]);

        $this->service()->advance($this->batch([$server]));

        $primary = Allocation::query()->where(['node_id' => $this->target->id, 'ip' => self::IP, 'port' => 25565])->firstOrFail();
        $transfer = ServerTransfer::query()->firstOrFail();

        $this->assertSame($primary->id, $transfer->new_allocation);
        $this->assertSame([$existing->id], $transfer->new_additional_allocations);
        $this->assertSame($server->id, $primary->server_id);
        $this->assertSame($server->id, $existing->refresh()->server_id);
    }

    public function test_batch_is_refused_when_a_port_is_in_use_on_the_chosen_ip(): void
    {
        $server = $this->serverOnPort(25565);
        $other = $this->createServerModel(['node_id' => $this->target->id]);
        Allocation::factory()->create(['node_id' => $this->target->id, 'ip' => self::IP, 'port' => 25565, 'server_id' => $other->id]);

        $this->expectException(TransferBatchException::class);
        $this->expectExceptionMessage(self::IP . ':25565');

        $this->batch([$server]);
    }

    /** @return array<string, array{string, string}> */
    public static function wildcardClashes(): array
    {
        return [
            'in use on all IPv4 addresses, chosen a specific one' => ['0.0.0.0', self::IP],
            'in use on a specific IPv4 address, chosen all of them' => [self::IP, '0.0.0.0'],
        ];
    }

    #[DataProvider('wildcardClashes')]
    public function test_batch_is_refused_when_a_port_clashes_with_the_ipv4_wildcard(string $inUse, string $chosen): void
    {
        $server = $this->serverOnPort(25565);
        $other = $this->createServerModel(['node_id' => $this->target->id]);
        Allocation::factory()->create(['node_id' => $this->target->id, 'ip' => '0.0.0.0', 'port' => 1]);
        Allocation::factory()->create(['node_id' => $this->target->id, 'ip' => $inUse, 'port' => 25565, 'server_id' => $other->id]);

        $this->expectException(TransferBatchException::class);
        $this->expectExceptionMessage("$chosen:25565");

        $this->batch([$server], $chosen);
    }

    public function test_batch_allows_the_same_port_on_different_specific_ips(): void
    {
        $server = $this->serverOnPort(25565);
        $other = $this->createServerModel(['node_id' => $this->target->id]);
        Allocation::factory()->create(['node_id' => $this->target->id, 'ip' => '10.0.0.6', 'port' => 25565, 'server_id' => $other->id]);

        $this->assertSame(1, $this->batch([$server])->items()->count());
    }

    public function test_server_fails_when_the_wildcard_took_its_port_while_it_was_queued(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        $batch = $this->batch([$server]);
        $other = $this->createServerModel(['node_id' => $this->target->id]);
        Allocation::factory()->create(['node_id' => $this->target->id, 'ip' => '0.0.0.0', 'port' => 25565, 'server_id' => $other->id]);

        $this->service()->advance($batch);

        $this->assertSame(ItemStatus::Failed, $batch->items()->sole()->status);
        $this->assertSame(0, ServerTransfer::query()->count());
    }

    public function test_batch_is_refused_when_two_of_its_servers_need_the_same_port(): void
    {
        $first = $this->serverOnPort(25565);
        $second = $this->serverOnPort(25565);

        $this->expectException(TransferBatchException::class);

        $this->batch([$first, $second]);
    }

    public function test_batch_is_refused_for_an_ip_that_is_not_on_the_target(): void
    {
        $server = $this->serverOnPort(25565);

        $this->expectException(TransferBatchException::class);

        $this->batch([$server], '203.0.113.9');
    }

    public function test_batch_is_refused_without_capacity_on_the_target(): void
    {
        $this->target->update(['memory' => 128, 'memory_overallocate' => 0]);
        $server = $this->serverOnPort(25565);
        $server->update(['memory' => 512]);

        $this->expectException(TransferBatchException::class);

        $this->batch([$server]);
    }

    public function test_a_node_can_only_be_in_one_running_batch(): void
    {
        $this->batch([$this->serverOnPort(25565)]);

        $this->expectException(TransferBatchException::class);

        $this->batch([$this->serverOnPort(25566)]);
    }

    public function test_running_server_is_stopped_first_and_started_again_after_moving(): void
    {
        $this->fakeDaemon('running');
        $server = $this->serverOnPort(25565);
        $batch = $this->batch([$server]);

        // Asked to stop, and the batch comes back to check on it.
        $this->assertTrue($this->service()->advance($batch));
        $item = $batch->items()->firstOrFail();
        $this->assertSame(ItemStatus::Stopping, $item->status);
        $this->assertTrue($item->was_running);
        $this->assertSame(0, ServerTransfer::query()->count());
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/power') && $request['action'] === 'stop');

        // Still running: keep waiting without starting the transfer.
        $this->assertTrue($this->service()->advance($batch));
        $this->assertSame(0, ServerTransfer::query()->count());

        // Now offline: the transfer starts.
        $this->daemonState = 'offline';
        $this->service()->advance($batch);
        $this->assertSame(1, ServerTransfer::query()->count());

        $this->transferSucceeds($server);
        $this->service()->advance($batch);

        Bus::assertDispatched(SendServerPowerAction::class, fn ($job) => $job->server->is($server) && $job->signal === 'start');
    }

    public function test_server_is_left_alone_while_the_node_does_not_say_what_state_it_is_in(): void
    {
        $this->fakeDaemon('running');
        $this->sourceAnswersState = false;
        $server = $this->serverOnPort(25565);
        $batch = $this->batch([$server]);
        $item = $batch->items()->sole();

        $this->assertFalse($this->service()->advance($batch));

        $this->assertSame(0, ServerTransfer::query()->count());
        $this->assertSame(ItemStatus::Queued, $item->refresh()->status);
        $this->assertNotNull($item->error);

        // Found running once it answers, so it gets started again after moving.
        $this->sourceAnswersState = true;
        $this->service()->advance($batch);
        $this->assertTrue($item->refresh()->was_running);
    }

    public function test_server_is_left_alone_when_the_node_reports_a_state_it_does_not_recognize(): void
    {
        $this->fakeDaemon('something-new');
        $batch = $this->batch([$this->serverOnPort(25565)]);

        $this->assertFalse($this->service()->advance($batch));

        $this->assertSame(0, ServerTransfer::query()->count());
        $this->assertSame(ItemStatus::Queued, $batch->items()->sole()->status);
    }

    public function test_restarting_server_is_started_again_after_moving(): void
    {
        $this->fakeDaemon('restarting');
        $batch = $this->batch([$this->serverOnPort(25565)]);

        $this->service()->advance($batch);

        $item = $batch->items()->sole();
        $this->assertSame(ItemStatus::Stopping, $item->status);
        $this->assertTrue($item->was_running);
    }

    public function test_same_port_on_two_ips_of_the_source_moves_as_one_port(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        Allocation::factory()->create(['node_id' => $this->source->id, 'ip' => '192.0.2.10', 'port' => 25565, 'server_id' => $server->id]);

        $batch = $this->batch([$server]);
        $this->service()->advance($batch);

        $transfer = ServerTransfer::query()->sole();
        $this->assertSame(ItemStatus::Transferring, $batch->items()->sole()->status);
        $this->assertSame([], $transfer->new_additional_allocations);
        $this->assertSame(25565, Allocation::query()->findOrFail($transfer->new_allocation)->port);
    }

    public function test_server_the_node_does_not_have_fails_instead_of_waiting_forever(): void
    {
        Http::fake([
            '*/api/system' => $this->systemInformation(),
            '*' => Http::response(status: 404, headers: ['User-Agent' => 'Pelican Wings']),
        ]);
        $batch = $this->batch([$this->serverOnPort(25565)]);

        $this->assertTrue($this->service()->advance($batch));

        $this->assertSame(ItemStatus::Failed, $batch->items()->sole()->status);
    }

    public function test_server_is_started_again_when_the_answer_to_its_stop_got_lost(): void
    {
        $this->fakeDaemon('running');
        $this->sourceAnswersPower = false;
        $server = $this->serverOnPort(25565);
        $batch = $this->batch([$server]);
        $item = $batch->items()->sole();

        $this->assertFalse($this->service()->advance($batch));
        $this->assertTrue($item->refresh()->was_running);

        // It did stop. Asking it to stop again while it does must not forget that it was running.
        $this->sourceAnswersPower = true;
        $this->daemonState = 'stopping';
        $this->service()->advance($batch);
        $this->assertTrue($item->refresh()->was_running);

        $this->daemonState = 'offline';
        $this->service()->advance($batch);
        $this->transferSucceeds($server);
        $this->service()->advance($batch);

        Bus::assertDispatched(SendServerPowerAction::class, fn ($job) => $job->server->is($server) && $job->signal === 'start');
    }

    public function test_server_that_never_stops_fails_without_transferring(): void
    {
        $this->fakeDaemon('running');
        $batch = $this->batch([$this->serverOnPort(25565)]);

        $this->service()->advance($batch);
        $this->travel(6)->minutes();
        $this->service()->advance($batch);

        $this->assertSame(ItemStatus::Failed, $batch->items()->firstOrFail()->status);
        $this->assertSame(0, ServerTransfer::query()->count());
    }

    public function test_failed_transfer_does_not_stop_the_batch(): void
    {
        $this->fakeDaemon();
        $first = $this->serverOnPort(25565);
        $second = $this->serverOnPort(25566);
        $batch = $this->batch([$first, $second]);

        $this->service()->advance($batch);
        ServerTransfer::query()->firstOrFail()->markFailed();

        $this->assertTrue($this->service()->advance($batch));
        $this->service()->advance($batch);

        $this->assertSame(ItemStatus::Failed, $batch->items()->where('server_id', $first->id)->first()->status);
        $this->assertSame(ItemStatus::Transferring, $batch->items()->where('server_id', $second->id)->first()->status);
    }

    public function test_unreachable_node_keeps_the_server_queued_and_leaves_nothing_behind(): void
    {
        Http::fake([
            '*/transfer' => fn () => throw new ConnectionException('down'),
            '*/api/system' => $this->systemInformation(),
            '*' => Http::response(['state' => 'offline']),
        ]);
        $server = $this->serverOnPort(25565);
        $batch = $this->batch([$server]);

        $this->assertFalse($this->service()->advance($batch));

        $item = $batch->items()->firstOrFail();
        $this->assertSame(ItemStatus::Queued, $item->status);
        $this->assertSame('down', $item->error);
        $this->assertNull($server->refresh()->transfer);
    }

    public function test_daemon_refusing_a_transfer_fails_the_item_and_unlocks_the_server(): void
    {
        Http::fake([
            '*/transfer' => Http::response(status: 500),
            '*/api/system' => $this->systemInformation(),
            '*' => Http::response(['state' => 'offline']),
        ]);
        $server = $this->serverOnPort(25565);
        $batch = $this->batch([$server]);

        $this->assertTrue($this->service()->advance($batch));

        $this->assertSame(ItemStatus::Failed, $batch->items()->firstOrFail()->status);
        $this->assertNull($server->refresh()->transfer);
        $this->assertSame(1, $server->allocations()->count());
    }

    public function test_transfer_that_never_reports_back_times_out(): void
    {
        $this->fakeDaemon();
        $batch = $this->batch([$this->serverOnPort(25565)]);
        $this->service()->advance($batch);
        Http::fake(['*' => Http::response(status: 204)]);

        $this->assertFalse($this->service()->advance($batch));

        $this->travel(config('panel.transfers.batch_timeout') + 1)->minutes();

        $this->assertTrue($this->service()->advance($batch));
        $this->assertSame(ItemStatus::Failed, $batch->items()->firstOrFail()->status);
        $this->assertFalse(ServerTransfer::query()->firstOrFail()->successful);
    }

    public function test_moving_backups_sends_only_the_local_ones_and_keeps_them(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        ['local' => $local, 'remote' => $remote] = $this->backupsOf($server);
        $batch = $this->batch([$server]);

        $this->service()->advance($batch);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfer') && $request['backups'] === [$local->uuid]);

        $this->transferSucceeds($server);
        $this->service()->advance($batch);

        $this->assertModelExists($local);
        $this->assertModelExists($remote);
    }

    public function test_not_moving_backups_deletes_only_local_ones_and_only_after_success(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        ['local' => $local, 'remote' => $remote] = $this->backupsOf($server);
        $batch = $this->batch([$server], moveBackups: false);

        $this->service()->advance($batch);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfer') && $request['backups'] === []);
        $this->assertModelExists($local);

        $this->transferSucceeds($server);
        $this->service()->advance($batch);

        $this->assertModelMissing($local);
        $this->assertModelExists($remote);
    }

    public function test_not_moving_backups_keeps_only_those_made_after_the_transfer(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        ['local' => $old] = $this->backupsOf($server);
        $batch = $this->batch([$server], moveBackups: false);

        $this->service()->advance($batch);

        // Made on the old node while the transfer ran, so its files aren't coming along either.
        $this->travel(1)->minutes();
        $during = Backup::factory()->create(['server_id' => $server->id, 'backup_host_id' => $old->backup_host_id]);

        $this->travel(1)->minutes();
        $this->transferSucceeds($server);

        $this->travel(1)->minutes();
        $after = Backup::factory()->create(['server_id' => $server->id, 'backup_host_id' => $old->backup_host_id]);

        $this->service()->advance($batch);

        $this->assertModelMissing($old);
        $this->assertModelMissing($during);
        $this->assertModelExists($after);
    }

    public function test_cancelling_a_transfer_that_already_finished_counts_as_moved(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        ['local' => $local] = $this->backupsOf($server);
        $batch = $this->batch([$server], moveBackups: false);
        $this->service()->advance($batch);
        $this->transferSucceeds($server);

        $this->service()->cancel($batch);

        $this->assertSame(BatchStatus::Cancelled, $batch->refresh()->status);
        $this->assertSame(ItemStatus::Successful, $batch->items()->sole()->status);
        $this->assertModelMissing($local);
    }

    public function test_failed_transfer_keeps_local_backups_it_would_have_deleted(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        ['local' => $local] = $this->backupsOf($server);
        $batch = $this->batch([$server], moveBackups: false);

        $this->service()->advance($batch);
        $server->refresh()->transfer->markFailed();
        $this->service()->advance($batch);

        $this->assertModelExists($local);
    }

    public function test_cancel_aborts_the_current_transfer_and_clears_the_queue(): void
    {
        $this->fakeDaemon();
        $first = $this->serverOnPort(25565);
        $second = $this->serverOnPort(25566);
        $batch = $this->batch([$first, $second]);
        $this->service()->advance($batch);

        $this->service()->cancel($batch);

        $this->assertSame(BatchStatus::Cancelled, $batch->refresh()->status);
        $this->assertSame(0, $batch->items()->whereNot('status', ItemStatus::Cancelled)->count());
        $this->assertNull($first->refresh()->transfer);
        $this->assertFalse($this->service()->advance($batch));
        $this->assertSame(1, ServerTransfer::query()->count());
    }

    public function test_servers_keep_running_while_the_target_is_unreachable(): void
    {
        $this->fakeDaemon('running');
        $this->targetIsUp = false;
        $server = $this->serverOnPort(25565);
        $batch = $this->batch([$server]);
        $item = $batch->items()->sole();

        $this->assertFalse($this->service()->advance($batch));
        $this->assertFalse($this->service()->advance($batch));

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/power'));
        $this->assertSame(0, ServerTransfer::query()->count());
        $this->assertSame(BatchStatus::Running, $batch->refresh()->status);
        $this->assertSame(ItemStatus::Queued, $item->refresh()->status);
        $this->assertNotNull($item->error);
        $this->assertNull($item->stop_requested_at);

        // Carries on by itself once the node is back
        $this->targetIsUp = true;
        $this->assertTrue($this->service()->advance($batch));

        $this->assertSame(ItemStatus::Stopping, $item->refresh()->status);
        $this->assertNull($item->error);
    }

    public function test_an_unreachable_target_can_still_be_cancelled(): void
    {
        $this->fakeDaemon('running');
        $this->targetIsUp = false;
        $batch = $this->batch([$this->serverOnPort(25565)]);
        $this->service()->advance($batch);

        $this->service()->cancel($batch);

        $this->assertSame(BatchStatus::Cancelled, $batch->refresh()->status);
        Bus::assertNotDispatched(SendServerPowerAction::class);
    }

    public function test_cancel_reaches_every_item_of_a_large_batch(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        $batch = $this->batch([$server]);
        $batch->items()->createMany(array_fill(0, 1001, ['server_id' => $server->id, 'status' => ItemStatus::Queued]));

        $this->service()->cancel($batch);

        $this->assertSame(0, $batch->items()->where('status', ItemStatus::Queued)->count());
    }

    public function test_node_cannot_be_deleted_during_a_batch_it_is_part_of(): void
    {
        $batch = $this->batch([$this->serverOnPort(25565)]);

        foreach ([$this->source, $this->target] as $node) {
            $this->assertTrue($node->isInRunningTransferBatch());
        }

        try {
            $this->target->delete();
            $this->fail('The target node was deleted during a running batch.');
        } catch (HasActiveServersException) {
            $this->assertModelExists($this->target);
            $this->assertModelExists($batch);
        }

        $batch->update(['status' => BatchStatus::Completed]);
        $this->target->allocations()->delete();
        $this->target->delete();

        $this->assertModelMissing($this->target);
    }

    public function test_transfer_whose_start_timed_out_is_waited_on_like_any_other(): void
    {
        $this->fakeDaemon();
        $this->transferFailure = $this->readTimeout();
        $server = $this->serverOnPort(25565);
        $batch = $this->batch([$server]);
        $item = $batch->items()->sole();

        $this->assertFalse($this->service()->advance($batch));

        $this->assertSame(ItemStatus::Transferring, $item->refresh()->status);
        $this->assertNotNull($item->error);
        $this->assertNull(ServerTransfer::query()->sole()->successful);
        $this->assertFalse($this->service()->advance($batch));

        $this->transferSucceeds($server);
        $this->service()->advance($batch);

        $this->assertSame(ItemStatus::Successful, $item->refresh()->status);
        $this->assertNull($item->error);
    }

    public function test_batch_timeout_keeps_trying_until_the_node_confirms_the_abort(): void
    {
        $this->fakeDaemon();
        $this->transferFailure = $this->readTimeout();
        $batch = $this->batch([$this->serverOnPort(25565)]);
        $item = $batch->items()->sole();
        $this->service()->advance($batch);

        $this->travel(config('panel.transfers.batch_timeout') + 1)->minutes();

        $this->assertFalse($this->service()->advance($batch));
        $this->assertSame(ItemStatus::Transferring, $item->refresh()->status);
        $this->assertNull(ServerTransfer::query()->sole()->successful);

        $this->transferFailure = null;

        $this->assertTrue($this->service()->advance($batch));
        $this->assertSame(ItemStatus::Failed, $item->refresh()->status);
        $this->assertFalse(ServerTransfer::query()->sole()->successful);
    }

    public function test_cancel_clears_the_queue_and_keeps_watching_a_transfer_it_could_not_abort(): void
    {
        $this->fakeDaemon();
        $first = $this->serverOnPort(25565);
        $second = $this->serverOnPort(25566);
        $batch = $this->batch([$first, $second]);
        $this->service()->advance($batch);

        $this->transferFailure = $this->readTimeout();

        $this->assertFalse($this->service()->cancel($batch));

        $this->assertSame(BatchStatus::Running, $batch->refresh()->status);
        $this->assertSame(ItemStatus::Transferring, $batch->items()->where('server_id', $first->id)->first()->status);
        $this->assertSame(ItemStatus::Cancelled, $batch->items()->where('server_id', $second->id)->first()->status);
        $this->assertNull(ServerTransfer::query()->sole()->successful);

        // It went through after all, which is what the batch was still there to notice.
        $this->transferSucceeds($first);
        $this->assertTrue($this->service()->advance($batch));
        $this->assertFalse($this->service()->advance($batch));

        $this->assertSame(ItemStatus::Successful, $batch->items()->where('server_id', $first->id)->first()->status);
        $this->assertSame(BatchStatus::Cancelled, $batch->refresh()->status);
    }

    public function test_cancel_can_be_retried_after_the_node_did_not_confirm(): void
    {
        $this->fakeDaemon();
        $batch = $this->batch([$this->serverOnPort(25565)]);
        $this->service()->advance($batch);

        $this->transferFailure = $this->readTimeout();
        $this->assertFalse($this->service()->cancel($batch));

        $this->transferFailure = null;
        $this->assertTrue($this->service()->cancel($batch));

        $this->assertSame(BatchStatus::Cancelled, $batch->refresh()->status);
        $this->assertSame(ItemStatus::Cancelled, $batch->items()->sole()->status);
        $this->assertFalse(ServerTransfer::query()->sole()->successful);
    }

    public function test_abort_that_does_not_reach_the_node_keeps_the_transfer_open_until_giving_up(): void
    {
        $this->fakeDaemon();
        $batch = $this->batch([$this->serverOnPort(25565)]);
        $item = $batch->items()->sole();
        $this->service()->advance($batch);

        // Only the panel may have lost the node, which can still be sending the server to the target.
        $this->transferFailure = new ConnectionException('refused', 0, new ConnectException('refused', new Request('DELETE', 'http://node')));

        $this->assertFalse($this->service()->cancel($batch));
        $this->assertSame(ItemStatus::Transferring, $item->refresh()->status);
        $this->assertNull(ServerTransfer::query()->sole()->successful);

        $this->travel(config('panel.transfers.batch_timeout') * 2 + 1)->minutes();

        $this->assertTrue($this->service()->cancel($batch));
        $this->assertSame(BatchStatus::Cancelled, $batch->refresh()->status);
        $this->assertSame(ItemStatus::Cancelled, $item->refresh()->status);
        $this->assertFalse(ServerTransfer::query()->sole()->successful);
    }

    public function test_batch_timeout_gives_up_on_a_node_that_never_answers_the_abort(): void
    {
        $this->fakeDaemon();
        $batch = $this->batch([$this->serverOnPort(25565)]);
        $item = $batch->items()->sole();
        $this->service()->advance($batch);
        $this->transferFailure = $this->readTimeout();

        $this->travel(config('panel.transfers.batch_timeout') + 1)->minutes();
        $this->assertFalse($this->service()->advance($batch));

        $this->travel(config('panel.transfers.batch_timeout'))->minutes();
        $this->assertTrue($this->service()->advance($batch));

        $this->assertSame(ItemStatus::Failed, $item->refresh()->status);
        $this->assertFalse(ServerTransfer::query()->sole()->successful);
    }

    public function test_abort_keeps_trying_without_a_batch_timeout(): void
    {
        config()->set('panel.transfers.batch_timeout', 0);
        $this->fakeDaemon();
        $batch = $this->batch([$this->serverOnPort(25565)]);
        $this->service()->advance($batch);
        $this->transferFailure = $this->readTimeout();

        $this->travel(1)->years();

        $this->assertFalse($this->service()->cancel($batch));
        $this->assertNull(ServerTransfer::query()->sole()->successful);
    }

    public function test_transfer_that_succeeds_while_it_is_being_aborted_counts_as_moved(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        ['local' => $local] = $this->backupsOf($server);
        $batch = $this->batch([$server], moveBackups: false);
        $this->service()->advance($batch);

        Http::fake(['*/transfer' => function () use ($server) {
            $this->transferSucceeds($server);

            return Http::response(status: 204);
        }]);

        $this->assertTrue($this->service()->cancel($batch));

        $this->assertSame(ItemStatus::Successful, $batch->items()->sole()->status);
        $this->assertTrue(ServerTransfer::query()->sole()->successful);
        $this->assertModelMissing($local);
    }

    public function test_a_step_of_the_batch_holds_the_lock_while_it_starts_a_transfer(): void
    {
        $this->fakeDaemon();
        $batch = $this->batch([$this->serverOnPort(25565)]);
        $wasLocked = null;

        Http::fake(['*/transfer' => function () use ($batch, &$wasLocked) {
            $wasLocked = !$this->lockOf($batch)->get();

            return Http::response(status: 202);
        }]);

        $this->service()->advance($batch);

        $this->assertTrue($wasLocked);
        $this->assertTrue($this->lockOf($batch)->get(), 'The lock should be released after the step.');
    }

    public function test_cancel_changes_nothing_when_a_step_keeps_the_batch_busy(): void
    {
        $this->fakeDaemon();
        $server = $this->serverOnPort(25565);
        $batch = $this->batch([$server]);
        $this->assertTrue($this->lockOf($batch)->get());

        Sleep::fake(syncWithCarbon: true);

        try {
            $this->service()->cancel($batch);
            $this->fail('Cancelling should have given up on the lock.');
        } catch (LockTimeoutException) {
            $this->assertSame(BatchStatus::Running, $batch->refresh()->status);
            $this->assertSame(ItemStatus::Queued, $batch->items()->sole()->status);
        }
    }

    public function test_batch_does_not_advance_while_it_is_being_cancelled(): void
    {
        $this->fakeDaemon();
        $batch = $this->batch([$this->serverOnPort(25565)]);
        $this->assertTrue($this->lockOf($batch)->get());

        $this->assertFalse($this->service()->advance($batch));

        $this->assertSame(0, ServerTransfer::query()->count());
    }

    private function lockOf(ServerTransferBatch $batch): Lock
    {
        return Cache::lock("server-transfer-batch:$batch->id", 120);
    }

    /** What the HTTP client throws when a node accepts the connection but never answers. */
    private function readTimeout(): ConnectionException
    {
        return new ConnectionException('timed out', 0, new NetworkTimeoutException('timed out', new Request('POST', 'http://node')));
    }

    public function test_cancel_leaves_a_finished_batch_alone(): void
    {
        $this->fakeDaemon();
        $batch = $this->batch([$this->serverOnPort(25565)]);
        $batch->update(['status' => BatchStatus::Completed]);

        $this->service()->cancel($batch);

        $this->assertSame(BatchStatus::Completed, $batch->refresh()->status);
    }

    private function transferSucceeds(Server $server): void
    {
        $transfer = $server->refresh()->transfer;
        $transfer->update(['successful' => true]);
        $server->update(['node_id' => $transfer->new_node, 'allocation_id' => $transfer->new_allocation]);
    }
}
