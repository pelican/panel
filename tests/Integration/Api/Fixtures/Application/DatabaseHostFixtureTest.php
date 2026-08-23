<?php

namespace App\Tests\Integration\Api\Fixtures\Application;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class DatabaseHostFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureAdmin();
    }

    public function test_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/database-hosts'));
    }

    public function test_view(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/database-hosts/100'));
    }

    public function test_view_with_includes(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/database-hosts/100?include=databases,nodes'));
    }
}
