<?php

use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;

it('uses the current primary allocation rather than a stale copy', function () {
    $server = createServerModel();
    $stale = Server::query()->findOrFail($server->id);
    $other = Allocation::factory()->forServer($server)->create();
    $kept = Allocation::factory()->forServer($server)->create();

    $server->releaseAllocations([$server->allocation_id]);
    expect($server->refresh()->allocation_id)->toBe($other->id);

    $stale->releaseAllocations([$other->id]);

    expect($server->refresh()->allocation_id)->toBe($kept->id);
});

it('picks a new primary allocation on the server\'s own node', function () {
    $server = createServerModel();
    Allocation::factory()->for($server)->for(Node::factory()->create())->create();
    $kept = Allocation::factory()->forServer($server)->create();

    $server->releaseAllocations([$server->allocation_id]);

    expect($server->refresh()->allocation_id)->toBe($kept->id);
});

it('does not release an allocation that now belongs to another server', function () {
    $server = createServerModel();
    $stale = Allocation::factory()->forServer($server)->create();
    $other = createServerModel(['node_id' => $server->node_id]);
    $stale->update(['server_id' => $other->id, 'notes' => 'mine']);

    $server->releaseAllocations([$stale->id]);

    expect($stale->refresh())
        ->server_id->toBe($other->id)
        ->notes->toBe('mine');
});
