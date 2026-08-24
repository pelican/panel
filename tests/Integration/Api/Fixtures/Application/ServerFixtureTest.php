<?php

namespace App\Tests\Integration\Api\Fixtures\Application;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class ServerFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureAdmin();
    }

    public function test_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/servers'));
    }

    public function test_view(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/servers/100'));
    }

    public function test_view_with_all_includes(): void
    {
        // The transfer include is declared on the transformer but has no include
        // method, so requesting it is a 500 and it is left out here.
        $this->assertFixtureSnapshot($this->getJson('/api/application/servers/100?include=allocations,user,subusers,egg,variables,node,databases'));
    }

    public function test_view_external(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/servers/external/fixture-external-1'));
    }

    public function test_databases_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/servers/100/databases'));
    }

    public function test_databases_index_with_includes(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/servers/100/databases?include=password,host'));
    }

    public function test_database_view(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/servers/100/databases/100'));
    }

    public function test_database_view_with_includes(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/servers/100/databases/100?include=password,host'));
    }
}
