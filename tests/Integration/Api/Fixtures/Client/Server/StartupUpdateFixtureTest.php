<?php

namespace App\Tests\Integration\Api\Fixtures\Client\Server;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class StartupUpdateFixtureTest extends FixtureTestCase
{
    private const BASE = '/api/client/servers/00000000-0000-4000-8000-000000000400';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureOwner();
    }

    public function test_update_variable(): void
    {
        $this->assertFixtureSnapshot($this->putJson(self::BASE . '/startup/variable', [
            'key' => 'SERVER_JARFILE',
            'value' => 'frozen.jar',
        ]));
    }
}
