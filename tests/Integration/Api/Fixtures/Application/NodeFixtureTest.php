<?php

namespace App\Tests\Integration\Api\Fixtures\Application;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class NodeFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureAdmin();
    }

    public function test_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/nodes'));
    }

    public function test_view(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/nodes/100'));
    }

    public function test_view_with_includes(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/nodes/100?include=allocations,servers'));
    }

    public function test_view_with_nested_include(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/nodes/100?include=servers.user'));
    }

    public function test_deployable(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/nodes/deployable?disk=100&memory=100'));
    }

    public function test_configuration(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/nodes/100/configuration'));
    }

    public function test_allocations_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/nodes/100/allocations'));
    }

    public function test_allocations_index_with_includes(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/nodes/100/allocations?include=node,server'));
    }
}
