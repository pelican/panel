<?php

namespace App\Tests\Integration\Api\Client\Server;

use App\Enums\SubuserPermission;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

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
}
