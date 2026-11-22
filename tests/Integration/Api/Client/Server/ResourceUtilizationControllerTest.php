<?php

namespace App\Tests\Integration\Api\Client\Server;

use App\Enums\SubuserPermission;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;
use Mockery\MockInterface;

class ResourceUtilizationControllerTest extends ClientApiIntegrationTestCase
{
    /**
     * Test that the resource utilization for a server is returned in the expected format.
     */
    public function test_server_resource_utilization_is_returned(): void
    {
        $service = \Mockery::mock(DaemonServerRepository::class);
        $this->app->instance(DaemonServerRepository::class, $service);

        [$user, $server] = $this->generateTestAccount([SubuserPermission::WebsocketConnect]);

        $service->expects('setServer')->with(\Mockery::on(function ($value) use ($server) {
            return $server->uuid === $value->uuid;
        }))->andReturnSelf()->getMock()->expects('getDetails')->andReturns([]);

        $response = $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/resources");

        $response->assertOk();
        $response->assertJson([
            'object' => 'stats',
            'attributes' => [
                'current_state' => 'stopped',
                'is_suspended' => false,
                'resources' => [
                    'memory_bytes' => 0,
                    'cpu_absolute' => 0,
                    'disk_bytes' => 0,
                    'network_rx_bytes' => 0,
                    'network_tx_bytes' => 0,
                    'disk_io_read_bytes' => 0,
                    'disk_io_write_bytes' => 0,
                ],
            ],
        ]);
    }

    /**
     * Test that a second request within the cache window is answered from the cache, without asking the daemon again.
     */
    public function test_repeated_requests_are_served_from_the_cache(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $this->mockDetails($server, ['state' => 'offline']);

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/resources")->assertOk();
        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/resources")
            ->assertOk()
            ->assertJsonPath('attributes.current_state', 'offline');
    }

    /**
     * Test that a container status update from the node clears the cache, so the next request returns the new state.
     */
    public function test_a_container_status_update_from_the_node_refreshes_the_cached_resources(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $this->mockDetails($server, ['state' => 'offline'], ['state' => 'running']);

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/resources")
            ->assertJsonPath('attributes.current_state', 'offline');

        $node = $server->node;
        $this->withHeader('Authorization', "Bearer $node->daemon_token_id." . $node->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/container/status", ['data' => ['new_state' => 'running']])
            ->assertOk();
        $this->flushHeaders();

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/resources")
            ->assertJsonPath('attributes.current_state', 'running');
    }

    /**
     * Test that sending a power action clears the cache, so the next request returns the new state.
     */
    public function test_a_power_action_refreshes_the_cached_resources(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $service = $this->mockDetails($server, ['state' => 'offline'], ['state' => 'starting']);
        $service->expects('power')->with('start');

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/resources")
            ->assertJsonPath('attributes.current_state', 'offline');

        $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/power", ['signal' => 'start'])
            ->assertNoContent();

        $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/resources")
            ->assertJsonPath('attributes.current_state', 'starting');
    }

    /**
     * Mocks the daemon so that each call to getDetails() returns the next of the given responses,
     * and fails the test if it's asked more (or fewer) times than that.
     *
     * @param  array<string, mixed>  ...$responses
     */
    private function mockDetails(Server $server, array ...$responses): MockInterface
    {
        $service = \Mockery::mock(DaemonServerRepository::class);
        $this->app->instance(DaemonServerRepository::class, $service);

        $service->allows('setServer')
            ->with(\Mockery::on(fn ($value) => $server->uuid === $value->uuid))
            ->andReturnSelf();
        $service->expects('getDetails')
            ->times(count($responses))
            ->andReturn(...$responses);

        return $service;
    }
}
