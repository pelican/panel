<?php

use App\Models\Allocation;

it('moves or clears primary allocations that were already released', function () {
    $moved = createServerModel();
    $released = $moved->allocation;
    $kept = Allocation::factory()->forServer($moved)->create();
    $released->update(Allocation::RELEASE_ATTRIBUTES);

    $cleared = createServerModel();
    $cleared->allocation->update(Allocation::RELEASE_ATTRIBUTES);

    $untouched = createServerModel();

    (require database_path('migrations/2026_10_10_000000_repair_released_primary_allocations.php'))->up();

    expect($moved->refresh()->allocation_id)->toBe($kept->id)
        ->and($cleared->refresh()->allocation_id)->toBeNull()
        ->and($untouched->refresh()->allocation_id)->toBe($untouched->allocation->id);
});
