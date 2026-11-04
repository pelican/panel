<?php

namespace App\Tests\Integration\Services\Servers;

use App\Enums\ServerTransferBatchItemStatus as ItemStatus;
use App\Enums\ServerTransferBatchStatus as BatchStatus;
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
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class ServerTransferBatchServiceTest extends IntegrationTestCase
{
    private const string IP = '10.0.0.5';

    private Node $source;

    private Node $target;

    private string $daemonState = 'offline';

    private bool $targetIsUp = true;

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
            '*/transfer' => Http::response(status: 202),
            '*/power' => Http::response(status: 204),
            '*/api/servers/*' => fn () => Http::response(['state' => $this->daemonState]),
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
