<?php

namespace App\Tests\Integration\Services\Servers;

use App\Exceptions\DisplayException;
use App\Models\Allocation;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Servers\BuildModificationService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\Client\ConnectionException;
use Mockery\MockInterface;

class BuildModificationServiceTest extends IntegrationTestCase
{
    private MockInterface $daemonServerRepository;

    /**
     * Setup tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->daemonServerRepository = $this->mock(DaemonServerRepository::class);
    }

    /**
     * Test that allocations can be added and removed from a server. Only the allocations on the
     * current node and belonging to this server should be modified.
     */
    public function test_allocations_can_be_modified_for_the_server(): void
    {
        $server = $this->createServerModel();
        $server2 = $this->createServerModel();

        /** @var Allocation[] $allocations */
        $allocations = Allocation::factory()->times(4)->create(['node_id' => $server->node_id, 'notes' => 'Random notes']);

        $initialAllocationId = $server->allocation_id;
        $allocations[0]->update(['server_id' => $server->id, 'notes' => 'Test notes', 'is_locked' => true, 'show_port' => false]);

        // Some additional test allocations for the other server, not the server we are attempting
        // to modify.
        $allocations[2]->update(['server_id' => $server2->id]);
        $allocations[3]->update(['server_id' => $server2->id]);

        $this->daemonServerRepository->expects('setServer->sync')->andReturnUndefined();

        $response = $this->getService()->handle($server, [
            // Attempt to add one new allocation, and an allocation assigned to another server. The
            // other server allocation should be ignored, and only the allocation for this server should
            // be used.
            'add_allocations' => [$allocations[2]->id, $allocations[1]->id],
            // Remove the default server allocation, ensuring that the new allocation passed through
            // in the data becomes the default allocation.
            'remove_allocations' => [$server->allocation_id, $allocations[0]->id, $allocations[3]->id],
        ]);

        $this->assertInstanceOf(Server::class, $response);

        // Only one allocation should exist for this server now.
        $this->assertCount(1, $response->allocations);
        $this->assertSame($allocations[1]->id, $response->allocation_id);
        $this->assertSame('Random notes', $response->allocation->notes);

        // These two allocations should not have been touched.
        $this->assertDatabaseHas('allocations', ['id' => $allocations[2]->id, 'server_id' => $server2->id]);
        $this->assertDatabaseHas('allocations', ['id' => $allocations[3]->id, 'server_id' => $server2->id]);

        // Both of these allocations should have been removed from the server, and have had their
        // per-server state (notes, lock, port visibility) properly reset.
        $this->assertDatabaseHas('allocations', ['id' => $initialAllocationId, 'server_id' => null, 'notes' => null]);
        $this->assertDatabaseHas('allocations', ['id' => $allocations[0]->id, 'server_id' => null, 'notes' => null, 'is_locked' => false, 'show_port' => true]);
    }

    /**
     * Test that the primary allocation can be removed.
     */
    public function test_primary_allocation_can_be_removed(): void
    {
        $server = $this->createServerModel();
        $server2 = $this->createServerModel();

        $server->allocation->update(['notes' => 'Random Notes']);
        $server2->allocation->update(['notes' => 'Random Notes']);

        $initialAllocationId = $server->allocation->id;

        $this->daemonServerRepository->expects('setServer->sync')->andReturnUndefined();

        $response = $this->getService()->handle($server, [
            // Remove the default server allocation, ensuring that the new allocation passed through
            // in the data becomes the default allocation.
            'remove_allocations' => [$server->allocation->id, $server2->allocation->id],
        ]);

        // No allocation should exist for this server now.
        $this->assertEmpty($response->allocations);
        $this->assertNull($response->allocation_id);

        // This allocation should not have been touched.
        $this->assertDatabaseHas('allocations', ['id' => $server2->allocation->id, 'server_id' => $server2->id, 'notes' => 'Random Notes']);

        // This allocation should have been removed from the server, and have had its
        // notes properly reset.
        $this->assertDatabaseHas('allocations', ['id' => $initialAllocationId, 'server_id' => null, 'notes' => null]);
    }

    /**
     * Test that the build data for the server is properly passed along to the daemon instance so that
     * the server data is updated in realtime. This test also ensures that only certain fields get updated
     * for the server, and not just any arbitrary field.
     */
    public function test_server_build_data_is_properly_updated_ondaemon(): void
    {
        $server = $this->createServerModel();

        $this->daemonServerRepository->expects('setServer')->with(\Mockery::on(function (Server $s) use ($server) {
            return $s->id === $server->id;
        }))->andReturnSelf();

        $this->daemonServerRepository->expects('sync')->withNoArgs()->andReturnUndefined();

        $response = $this->getService()->handle($server, [
            'oom_killer' => false,
            'memory' => 256,
            'swap' => 128,
            'io' => 600,
            'cpu' => 150,
            'threads' => '1,2',
            'disk' => 1024,
            'backup_limit' => null,
            'database_limit' => 10,
            'allocation_limit' => 20,
        ]);

        $this->assertFalse($response->oom_killer);
        $this->assertSame(256, $response->memory);
        $this->assertSame(128, $response->swap);
        $this->assertSame(600, $response->io);
        $this->assertSame(150, $response->cpu);
        $this->assertSame('1,2', $response->threads);
        $this->assertSame(1024, $response->disk);
        $this->assertSame(0, $response->backup_limit);
        $this->assertSame(10, $response->database_limit);
        $this->assertSame(20, $response->allocation_limit);
    }

    /**
     * Test that an exception when connecting to the Daemon instance is properly ignored
     * when making updates. This allows for a server to be modified even when the Daemon
     * node is offline.
     */
    public function test_connection_exception_is_ignored_when_updating_server_settings(): void
    {
        $this->markTestSkipped();

        $server = $this->createServerModel();

        $this->daemonServerRepository->expects('setServer->sync')->andThrows(new ConnectionException());

        $response = $this->getService()->handle($server, ['memory' => 256, 'disk' => 10240]);

        $this->assertInstanceOf(Server::class, $response);
        $this->assertSame(256, $response->memory);
        $this->assertSame(10240, $response->disk);

        $this->assertDatabaseHas('servers', ['id' => $response->id, 'memory' => 256, 'disk' => 10240]);
    }

    /**
     * Test that no exception is thrown if we are only removing an allocation.
     */
    public function test_no_exception_is_thrown_if_only_removing_allocation(): void
    {
        $server = $this->createServerModel();
        /** @var Allocation $allocation */
        $allocation = Allocation::factory()->create(['node_id' => $server->node_id, 'server_id' => $server->id]);

        $this->daemonServerRepository->expects('setServer->sync')->andReturnUndefined();

        $this->getService()->handle($server, [
            'remove_allocations' => [$allocation->id],
        ]);

        $this->assertDatabaseHas('allocations', ['id' => $allocation->id, 'server_id' => null]);
    }

    /**
     * Test that allocations in both the add and remove arrays are only added, and not removed.
     * This scenario wouldn't really happen in the UI, but it is possible to perform via the API,
     * so we want to make sure that the logic being used doesn't break if the allocation exists
     * in both arrays.
     *
     * We'll default to adding the allocation in this case.
     */
    public function test_allocation_in_both_add_and_remove_is_added(): void
    {
        $server = $this->createServerModel();
        /** @var Allocation $allocation */
        $allocation = Allocation::factory()->create(['node_id' => $server->node_id]);

        $this->daemonServerRepository->expects('setServer->sync')->andReturnUndefined();

        $this->getService()->handle($server, [
            'add_allocations' => [$allocation->id],
            'remove_allocations' => [$allocation->id],
        ]);

        $this->assertDatabaseHas('allocations', ['id' => $allocation->id, 'server_id' => $server->id]);
    }

    /**
     * Test that using the same allocation ID multiple times in the array does not cause an error.
     */
    public function test_using_same_allocation_id_multiple_times_does_not_error(): void
    {
        $server = $this->createServerModel();
        /** @var Allocation $allocation */
        $allocation = Allocation::factory()->create(['node_id' => $server->node_id, 'server_id' => $server->id]);
        /** @var Allocation $allocation2 */
        $allocation2 = Allocation::factory()->create(['node_id' => $server->node_id]);

        $this->daemonServerRepository->expects('setServer->sync')->andReturnUndefined();

        $this->getService()->handle($server, [
            'add_allocations' => [$allocation2->id, $allocation2->id],
            'remove_allocations' => [$allocation->id, $allocation->id],
        ]);

        $this->assertDatabaseHas('allocations', ['id' => $allocation->id, 'server_id' => null]);
        $this->assertDatabaseHas('allocations', ['id' => $allocation2->id, 'server_id' => $server->id]);
    }

    /**
     * Test that any changes we made to the server or allocations are rolled back if there is an
     * exception while performing any action. This is different from the connection exception
     * test which should properly ignore connection issues. We want any other type of exception
     * to properly be thrown back to the caller.
     */
    public function test_that_updates_are_rolled_back_if_exception_is_encountered(): void
    {
        $server = $this->createServerModel();
        /** @var Allocation $allocation */
        $allocation = Allocation::factory()->create(['node_id' => $server->node_id]);

        $this->daemonServerRepository->expects('setServer->sync')->andThrows(new DisplayException('Test'));

        $this->expectException(DisplayException::class);

        $this->getService()->handle($server, ['add_allocations' => [$allocation->id]]);

        $this->assertDatabaseHas('allocations', ['id' => $allocation->id, 'server_id' => null]);
    }

    private function getService(): BuildModificationService
    {
        return $this->app->make(BuildModificationService::class);
    }
}
