<?php

namespace App\Tests\Integration\Api\Fixtures\Client\Server;

use App\Repositories\Daemon\DaemonServerRepository;
use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class ResourceUtilizationFixtureTest extends FixtureTestCase
{
    private const BASE = '/api/client/servers/00000000-0000-4000-8000-000000000400';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureOwner();

        $mock = \Mockery::mock(DaemonServerRepository::class);
        $this->app->instance(DaemonServerRepository::class, $mock);

        $mock->allows('setServer')->andReturnSelf();
        $mock->allows('getDetails')->andReturns([
            'state' => 'running',
            'is_suspended' => false,
            'utilization' => [
                'memory_bytes' => 536870912,
                'cpu_absolute' => 12,
                'disk_bytes' => 1073741824,
                'network' => [
                    'rx_bytes' => 1024,
                    'tx_bytes' => 2048,
                ],
                'uptime' => 60000,
            ],
        ]);
    }

    public function test_resources(): void
    {
        $this->assertFixtureSnapshot($this->getJson(self::BASE . '/resources'));
    }
}
