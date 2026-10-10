<?php

namespace App\Tests\Integration\Migrations;

use App\Models\Allocation;
use App\Tests\Integration\IntegrationTestCase;

class RepairReleasedPrimaryAllocationsMigrationTest extends IntegrationTestCase
{
    public function test_released_primary_allocations_are_moved_or_cleared(): void
    {
        $moved = $this->createServerModel();
        $released = $moved->allocation;
        $kept = Allocation::factory()->forServer($moved)->create();
        $released->update(Allocation::RELEASE_ATTRIBUTES);

        $cleared = $this->createServerModel();
        $cleared->allocation->update(Allocation::RELEASE_ATTRIBUTES);

        $untouched = $this->createServerModel();

        (require database_path('migrations/2026_10_10_000000_repair_released_primary_allocations.php'))->up();

        $this->assertSame($kept->id, $moved->refresh()->allocation_id);
        $this->assertNull($cleared->refresh()->allocation_id);
        $this->assertSame($untouched->allocation->id, $untouched->refresh()->allocation_id);
    }
}
