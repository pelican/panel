<?php

namespace App\Tests\Integration\Api\Application;

use App\Models\Allocation;
use App\Models\Node;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Http\Response;

class AllocationControllerTest extends ApplicationApiIntegrationTestCase
{
    private Node $node;

    protected function setUp(): void
    {
        parent::setUp();

        $this->node = Node::factory()->create();
    }

    public function test_list_node_allocations(): void
    {
        $allocations = Allocation::factory(2)->create(['node_id' => $this->node->id]);

        $response = $this->getJson("/api/application/nodes/{$this->node->id}/allocations");
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');

        foreach ($allocations as $allocation) {
            $response->assertJsonFragment(['id' => $allocation->id, 'port' => $allocation->port]);
        }
    }

    public function test_create_allocations_for_single_ports_and_ranges(): void
    {
        $this->postJson("/api/application/nodes/{$this->node->id}/allocations", [
            'ip' => '10.0.0.1',
            'ports' => ['25565', '25570-25572'],
        ])->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertEqualsCanonicalizing(
            [25565, 25570, 25571, 25572],
            Allocation::query()->where('node_id', $this->node->id)->where('ip', '10.0.0.1')->pluck('port')->all(),
        );
    }

    public function test_create_allocations_requires_ports(): void
    {
        $this->postJson("/api/application/nodes/{$this->node->id}/allocations", [
            'ip' => '10.0.0.1',
        ])->assertUnprocessable();
    }

    public function test_delete_unassigned_allocation(): void
    {
        $allocation = Allocation::factory()->create(['node_id' => $this->node->id, 'server_id' => null]);

        $this->deleteJson("/api/application/nodes/{$this->node->id}/allocations/{$allocation->id}")
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('allocations', ['id' => $allocation->id]);
    }

    public function test_assigned_allocation_cannot_be_deleted(): void
    {
        $server = $this->createServerModel(['node_id' => $this->node->id]);

        $this->deleteJson("/api/application/nodes/{$this->node->id}/allocations/{$server->allocation_id}")
            ->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('allocations', ['id' => $server->allocation_id]);
    }

    public function test_error_returned_if_no_permission(): void
    {
        $this->createNewDefaultApiKey($this->getApiUser(), [Allocation::RESOURCE_NAME => AdminAcl::NONE]);

        $response = $this->getJson("/api/application/nodes/{$this->node->id}/allocations");
        $this->assertAccessDeniedJson($response);
    }
}
