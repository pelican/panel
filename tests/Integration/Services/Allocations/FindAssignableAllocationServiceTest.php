<?php

namespace App\Tests\Integration\Services\Allocations;

use App\Exceptions\DisplayException;
use App\Exceptions\Service\Allocation\AutoAllocationNotEnabledException;
use App\Exceptions\Service\Allocation\NoAutoAllocationSpaceAvailableException;
use App\Models\Allocation;
use App\Services\Allocations\FindAssignableAllocationService;
use App\Tests\Integration\IntegrationTestCase;

class FindAssignableAllocationServiceTest extends IntegrationTestCase
{
    /**
     * Setup tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('panel.client_features.allocations.enabled', true);
        config()->set('panel.client_features.allocations.range_start', 0);
        config()->set('panel.client_features.allocations.range_end', 0);
    }

    /**
     * Test that an unassigned allocation is preferred rather than creating an entirely new
     * allocation for the server.
     */
    public function test_existing_allocation_is_preferred(): void
    {
        $server = $this->createServerModel();
        config()->set('panel.client_features.allocations.range_start', 5000);
        config()->set('panel.client_features.allocations.range_end', 5005);

        $created = Allocation::factory()->create([
            'node_id' => $server->node_id,
            'ip' => $server->allocation->ip,
            'port' => 5005,
        ]);

        $response = $this->getService()->handle($server);

        $this->assertSame($created->id, $response->id);
        $this->assertSame($server->allocation->ip, $response->ip);
        $this->assertSame($server->node_id, $response->node_id);
        $this->assertSame($server->id, $response->server_id);
        $this->assertNotSame($server->allocation_id, $response->id);
    }

    /**
     * Test that a new allocation is created if there is not a free one available.
     */
    public function test_new_allocation_is_created_if_one_is_not_found(): void
    {
        $server = $this->createServerModel();
        config()->set('panel.client_features.allocations.range_start', 5000);
        config()->set('panel.client_features.allocations.range_end', 5005);

        $response = $this->getService()->handle($server);
        $this->assertSame($server->id, $response->server_id);
        $this->assertSame($server->allocation->ip, $response->ip);
        $this->assertSame($server->node_id, $response->node_id);
        $this->assertNotSame($server->allocation_id, $response->id);
        $this->assertTrue($response->port >= 5000 && $response->port <= 5005);
    }

    /**
     * Test that a currently assigned port is never assigned to a server.
     */
    public function test_only_port_not_in_use_is_created(): void
    {
        $server = $this->createServerModel();
        $server2 = $this->createServerModel(['node_id' => $server->node_id]);

        config()->set('panel.client_features.allocations.range_start', 5000);
        config()->set('panel.client_features.allocations.range_end', 5001);

        Allocation::factory()->create([
            'server_id' => $server2->id,
            'node_id' => $server->node_id,
            'ip' => $server->allocation->ip,
            'port' => 5000,
        ]);

        $response = $this->getService()->handle($server);
        $this->assertSame(5001, $response->port);
    }

    public function test_exception_is_thrown_if_no_more_allocations_can_be_created_in_range(): void
    {
        $server = $this->createServerModel();
        $server2 = $this->createServerModel(['node_id' => $server->node_id]);
        config()->set('panel.client_features.allocations.range_start', 5000);
        config()->set('panel.client_features.allocations.range_end', 5005);

        for ($i = 5000; $i <= 5005; $i++) {
            Allocation::factory()->create([
                'ip' => $server->allocation->ip,
                'port' => $i,
                'node_id' => $server->node_id,
                'server_id' => $server2->id,
            ]);
        }

        $this->expectException(NoAutoAllocationSpaceAvailableException::class);
        $this->expectExceptionMessage('Cannot assign additional allocation: no more space available on node.');

        $this->getService()->handle($server);
    }

    /**
     * Test that we only auto-allocate from the current server's IP address space, and not a random
     * IP address available on that node.
     */
    public function test_exception_is_thrown_if_only_free_port_is_on_a_different_ip(): void
    {
        $server = $this->createServerModel();

        Allocation::factory()->times(5)->create(['node_id' => $server->node_id]);

        $this->expectException(NoAutoAllocationSpaceAvailableException::class);
        $this->expectExceptionMessage('Cannot assign additional allocation: no more space available on node.');

        $this->getService()->handle($server);
    }

    public function test_exception_is_thrown_if_start_or_end_range_is_not_defined(): void
    {
        $server = $this->createServerModel();

        $this->expectException(NoAutoAllocationSpaceAvailableException::class);
        $this->expectExceptionMessage('Cannot assign additional allocation: no more space available on node.');

        $this->getService()->handle($server);
    }

    public function test_exception_is_thrown_if_start_or_end_range_is_not_numeric(): void
    {
        $server = $this->createServerModel();
        config()->set('panel.client_features.allocations.range_start', 'hodor');
        config()->set('panel.client_features.allocations.range_end', 10);

        try {
            $this->getService()->handle($server);
            $this->fail('This assertion should not be reached.');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
            $this->assertSame('Expected an integerish value. Got: string', $exception->getMessage());
        }

        config()->set('panel.client_features.allocations.range_start', 10);
        config()->set('panel.client_features.allocations.range_end', 'hodor');

        try {
            $this->getService()->handle($server);
            $this->fail('This assertion should not be reached.');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
            $this->assertSame('Expected an integerish value. Got: string', $exception->getMessage());
        }
    }

    public function test_exception_is_thrown_if_feature_is_not_enabled(): void
    {
        config()->set('panel.client_features.allocations.enabled', false);
        $server = $this->createServerModel();

        $this->expectException(AutoAllocationNotEnabledException::class);

        $this->getService()->handle($server);
    }

    public function test_exception_is_thrown_if_server_has_no_primary_allocation(): void
    {
        $server = $this->createServerModel();
        $server->update(['allocation_id' => null]);

        $this->expectException(DisplayException::class);

        $this->getService()->handle($server->refresh());
    }

    private function getService(): FindAssignableAllocationService
    {
        return $this->app->make(FindAssignableAllocationService::class);
    }
}
