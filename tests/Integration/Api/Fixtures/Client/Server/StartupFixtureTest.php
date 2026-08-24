<?php

namespace App\Tests\Integration\Api\Fixtures\Client\Server;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class StartupFixtureTest extends FixtureTestCase
{
    private const BASE = '/api/client/servers/00000000-0000-4000-8000-000000000400';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureOwner();
    }

    public function test_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson(self::BASE . '/startup'));
    }
}
