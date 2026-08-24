<?php

namespace App\Tests\Integration\Api\Fixtures\Application;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class MountFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureAdmin();
    }

    public function test_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/mounts'));
    }

    public function test_view(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/mounts/100'));
    }

    public function test_view_with_includes(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/mounts/100?include=eggs,nodes,servers'));
    }

    public function test_eggs(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/mounts/100/eggs'));
    }

    public function test_nodes(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/mounts/100/nodes'));
    }

    public function test_servers(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/mounts/100/servers'));
    }
}
