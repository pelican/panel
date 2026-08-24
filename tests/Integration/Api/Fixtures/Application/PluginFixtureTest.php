<?php

namespace App\Tests\Integration\Api\Fixtures\Application;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class PluginFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureAdmin();
    }

    public function test_index(): void
    {
        // Plugin is a Sushi model whose rows come from scanning the local plugins/
        // directory, so the response depends on the machine, not on the pinned world.
        // A snapshot recorded here would fail on any host with different plugins.
        $this->markTestSkipped('Plugin list is filesystem-derived and not pinnable by the fixture world.');
    }
}
