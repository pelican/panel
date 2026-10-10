<?php

namespace App\Tests\Integration\Models;

use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

class ServerReleaseAllocationsTest extends IntegrationTestCase
{
    /**
     * Test that a release uses the primary allocation stored in the database rather than
     * a stale copy, so concurrent releases can't leave the primary on a released allocation.
     */
    public function test_release_uses_the_current_primary_allocation(): void
    {
        $server = $this->createServerModel();
        $stale = Server::query()->findOrFail($server->id);
        $other = Allocation::factory()->forServer($server)->create();
        $kept = Allocation::factory()->forServer($server)->create();

        $server->releaseAllocations([$server->allocation_id]);
        $this->assertSame($other->id, $server->refresh()->allocation_id);

        $stale->releaseAllocations([$other->id]);

        $this->assertSame($kept->id, $server->refresh()->allocation_id);
    }

    /**
     * Test that the new primary allocation is on the server's current node, so an allocation
     * reserved on the target node of a transfer is never picked.
     */
    public function test_new_primary_allocation_is_on_the_servers_node(): void
    {
        $server = $this->createServerModel();
        $target = Node::factory()->create();
        Allocation::factory()->for($server)->for($target)->create();
        $kept = Allocation::factory()->forServer($server)->create();

        $server->releaseAllocations([$server->allocation_id]);

        $this->assertSame($kept->id, $server->refresh()->allocation_id);
    }
}
