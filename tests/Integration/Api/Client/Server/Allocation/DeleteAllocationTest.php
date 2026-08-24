<?php

namespace App\Tests\Integration\Api\Client\Server\Allocation;

use App\Enums\SubuserPermission;
use App\Models\Allocation;
use App\Models\Server;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;

class DeleteAllocationTest extends ClientApiIntegrationTestCase
{
    /**
     * Test that an allocation is deleted from the server and the notes are properly reset
     * to an empty value on assignment.
     */
    #[DataProvider('permissionDataProvider')]
    public function test_allocation_can_be_deleted_from_server(array $permission): void
    {
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount($permission);
        $server->update(['allocation_limit' => 2]);

        /** @var Allocation $allocation */
        $allocation = Allocation::factory()->create([
            'server_id' => $server->id,
            'node_id' => $server->node_id,
            'is_locked' => true,
            'notes' => 'hodor',
            'show_port' => false,
        ]);

        $this->actingAs($user)->deleteJson($this->link($allocation))->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseHas('allocations', [
            'id' => $allocation->id,
            'is_locked' => false,
            'server_id' => null,
            'notes' => null,
            'show_port' => true,
        ]);
    }

    /**
     * Test that an allocation is deleted if it is currently marked as the primary allocation
     * for the server.
     */
    public function test_primary_allocation_can_be_deleted_from_server(): void
    {
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount();
        $server->update(['allocation_limit' => 2]);

        $allocation = $server->allocation;

        $this->actingAs($user)->deleteJson($this->link($allocation))->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseHas('allocations', ['id' => $allocation->id, 'server_id' => null, 'notes' => null]);
    }

    /**
     * Test that an error is returned if the user does not have permissiont to delete an allocation.
     */
    public function test_error_is_returned_if_user_does_not_have_permission(): void
    {
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount([SubuserPermission::AllocationCreate]);

        /** @var Allocation $allocation */
        $allocation = Allocation::factory()->create([
            'server_id' => $server->id,
            'node_id' => $server->node_id,
            'notes' => 'hodor',
        ]);

        $this->actingAs($user)->deleteJson($this->link($allocation))->assertForbidden();

        $this->assertDatabaseHas('allocations', ['id' => $allocation->id, 'server_id' => $server->id]);
    }

    public function test_allocation_cannot_be_deleted_if_server_limit_is_not_defined(): void
    {
        [$user, $server] = $this->generateTestAccount();

        /** @var Allocation $allocation */
        $allocation = Allocation::factory()->forServer($server)->create(['notes' => 'Test notes']);

        $this->actingAs($user)->deleteJson($this->link($allocation))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.detail', 'You cannot delete allocations for this server: no allocation limit is set.');

        $allocation->refresh();
        $this->assertNotNull($allocation->notes);
        $this->assertEquals($server->id, $allocation->server_id);
    }

    /**
     * Test that an allocation cannot be deleted if it does not belong to the server instance.
     */
    public function test_error_is_returned_if_allocation_does_not_belong_to_server(): void
    {
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount();
        [, $server2] = $this->generateTestAccount();

        $this->actingAs($user)->deleteJson($this->link($server2->allocation))->assertNotFound();
        $this->actingAs($user)->deleteJson($this->link($server, "/network/allocations/{$server2->allocation_id}"))->assertNotFound();
    }

    public static function permissionDataProvider(): array
    {
        return [[[SubuserPermission::AllocationDelete]], [[]]];
    }
}
