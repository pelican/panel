<?php

namespace App\Tests\Integration\Api\Application;

use App\Data\Api\Application\NodeData;
use App\Models\Node;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class NodeControllerTest extends ApplicationApiIntegrationTestCase
{
    /** @var array<string, mixed> */
    private array $storePayload = [
        'name' => 'api-node',
        'fqdn' => '10.0.0.1',
        'scheme' => 'http',
        'memory' => 0,
        'memory_overallocate' => 0,
        'disk' => 0,
        'disk_overallocate' => 0,
        'cpu' => 0,
        'cpu_overallocate' => 0,
        'daemon_sftp' => 2022,
        'daemon_listen' => 8080,
        'daemon_connect' => 8080,
        'upload_size' => 256,
    ];

    public function test_list_all_nodes(): void
    {
        $nodes = Node::factory(2)->create();

        $response = $this->getJson('/api/application/nodes');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');

        foreach ($nodes as $node) {
            $response->assertJsonFragment(['id' => $node->id]);
        }
    }

    public function test_return_single_node(): void
    {
        $node = Node::factory()->create();

        $response = $this->getJson('/api/application/nodes/' . $node->id);
        $response->assertStatus(Response::HTTP_OK);
        // Not strict: the response carries null fields the Data object omits.
        $response->assertJson([
            'object' => 'node',
            'attributes' => $this->getExpectedData(NodeData::class, $node),
        ]);
    }

    public function test_create_node(): void
    {
        $response = $this->postJson('/api/application/nodes', $this->storePayload);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('nodes', ['name' => 'api-node', 'fqdn' => '10.0.0.1']);

        $node = Node::query()->where('name', 'api-node')->firstOrFail();
        // The creation response omits attributes that are null, so compare without them.
        $response->assertJson([
            'object' => 'node',
            'attributes' => array_filter($this->getExpectedData(NodeData::class, $node), fn ($value) => $value !== null),
            'meta' => ['resource' => route('api.application.nodes.view', $node->id)],
        ]);
    }

    public function test_create_node_requires_fqdn(): void
    {
        $payload = $this->storePayload;
        unset($payload['fqdn']);

        $this->postJson('/api/application/nodes', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.meta.source_field', 'fqdn');
    }

    public function test_update_node(): void
    {
        // The update service pings Wings about the config change but tolerates failure.
        Http::fake();

        $node = Node::factory()->create();

        $response = $this->patchJson('/api/application/nodes/' . $node->id, array_merge($this->storePayload, [
            'name' => 'renamed-node',
        ]));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('nodes', ['id' => $node->id, 'name' => 'renamed-node']);
    }

    public function test_delete_node(): void
    {
        $node = Node::factory()->create();

        $this->deleteJson('/api/application/nodes/' . $node->id)
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('nodes', ['id' => $node->id]);
    }

    public function test_node_with_servers_cannot_be_deleted(): void
    {
        $server = $this->createServerModel();

        $this->deleteJson('/api/application/nodes/' . $server->node_id)
            ->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertDatabaseHas('nodes', ['id' => $server->node_id]);
    }

    public function test_error_returned_if_no_permission(): void
    {
        $node = Node::factory()->create();
        $this->createNewDefaultApiKey($this->getApiUser(), [Node::RESOURCE_NAME => AdminAcl::NONE]);

        $response = $this->getJson('/api/application/nodes/' . $node->id);
        $this->assertAccessDeniedJson($response);
    }

    public function test_api_key_without_write_permissions_cannot_create(): void
    {
        $this->createNewDefaultApiKey($this->getApiUser(), [Node::RESOURCE_NAME => AdminAcl::READ]);

        $response = $this->postJson('/api/application/nodes', $this->storePayload);
        $this->assertAccessDeniedJson($response);
    }
}
