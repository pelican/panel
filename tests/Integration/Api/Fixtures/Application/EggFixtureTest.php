<?php

namespace App\Tests\Integration\Api\Fixtures\Application;

use App\Models\Egg;
use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class EggFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        // The index endpoint returns every egg, and the seeded eggs carry random
        // uuids that differ per database, so only the pinned egg may remain.
        Egg::query()->whereKeyNot(100)->delete();
        $this->actingAsFixtureAdmin();
    }

    public function test_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/eggs'));
    }

    public function test_view(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/eggs/100'));
    }

    public function test_view_with_all_includes(): void
    {
        // The config and script includes are declared on the transformer but have no
        // include methods, so requesting them is a 500 and they are left out here.
        $this->assertFixtureSnapshot($this->getJson('/api/application/eggs/100?include=servers,variables'));
    }

    // The export endpoint streams a YAML download, not JSON, so it is not snapshotted here.
}
