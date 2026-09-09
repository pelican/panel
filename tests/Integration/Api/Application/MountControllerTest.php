<?php

namespace App\Tests\Integration\Api\Application;

use App\Data\Api\Application\MountData;
use App\Models\Egg;
use App\Models\Mount;
use App\Models\Node;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class MountControllerTest extends ApplicationApiIntegrationTestCase
{
    private function createMount(): Mount
    {
        return Mount::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Mount ' . Str::random(8),
            'source' => '/mnt/' . Str::random(8),
            'target' => '/srv/' . Str::random(8),
            'read_only' => false,
            'user_mountable' => false,
        ]);
    }

    public function test_list_all_mounts(): void
    {
        $mount = $this->createMount();

        $response = $this->getJson('/api/application/mounts');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $mount->id]);
    }

    public function test_return_single_mount(): void
    {
        $mount = $this->createMount();

        $response = $this->getJson('/api/application/mounts/' . $mount->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'object' => 'mount',
            'attributes' => $this->getExpectedData(MountData::class, $mount),
        ], true);
    }

    public function test_create_mount(): void
    {
        $response = $this->postJson('/api/application/mounts', [
            'name' => 'api-mount',
            'source' => '/mnt/api-source',
            'target' => '/srv/api-target',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('mounts', [
            'name' => 'api-mount',
            'source' => '/mnt/api-source',
            'target' => '/srv/api-target',
        ]);
    }

    public function test_create_mount_requires_source(): void
    {
        $this->postJson('/api/application/mounts', [
            'name' => 'api-mount',
            'target' => '/srv/api-target',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('mounts', ['name' => 'api-mount']);
    }

    public function test_update_mount(): void
    {
        $mount = $this->createMount();

        $response = $this->patchJson('/api/application/mounts/' . $mount->id, [
            'name' => 'renamed-mount',
            'source' => $mount->source,
            'target' => $mount->target,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('mounts', ['id' => $mount->id, 'name' => 'renamed-mount']);
    }

    public function test_mount_egg_and_node_relations_can_be_managed(): void
    {
        $mount = $this->createMount();
        $egg = Egg::query()->firstOrFail();
        $node = Node::factory()->create();

        $eggRow = ['mount_id' => $mount->id, 'mountable_type' => 'egg', 'mountable_id' => $egg->id];
        $nodeRow = ['mount_id' => $mount->id, 'mountable_type' => 'node', 'mountable_id' => $node->id];

        $this->postJson("/api/application/mounts/{$mount->id}/eggs", ['eggs' => [$egg->id]])
            ->assertSuccessful();
        $this->assertDatabaseHas('mountables', $eggRow);

        $this->postJson("/api/application/mounts/{$mount->id}/nodes", ['nodes' => [$node->id]])
            ->assertSuccessful();
        $this->assertDatabaseHas('mountables', $nodeRow);

        $this->deleteJson("/api/application/mounts/{$mount->id}/eggs/{$egg->id}")
            ->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertDatabaseMissing('mountables', $eggRow);

        $this->deleteJson("/api/application/mounts/{$mount->id}/nodes/{$node->id}")
            ->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertDatabaseMissing('mountables', $nodeRow);
    }

    public function test_delete_mount(): void
    {
        $mount = $this->createMount();

        $this->deleteJson('/api/application/mounts/' . $mount->id)
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('mounts', ['id' => $mount->id]);
    }

    public function test_error_returned_if_no_permission(): void
    {
        $mount = $this->createMount();
        $this->createNewDefaultApiKey($this->getApiUser(), [Mount::RESOURCE_NAME => AdminAcl::NONE]);

        $response = $this->getJson('/api/application/mounts/' . $mount->id);
        $this->assertAccessDeniedJson($response);
    }

    public function test_api_key_without_write_permissions_cannot_create(): void
    {
        $this->createNewDefaultApiKey($this->getApiUser(), [Mount::RESOURCE_NAME => AdminAcl::READ]);

        $response = $this->postJson('/api/application/mounts', [
            'name' => 'api-mount',
            'source' => '/mnt/api-source',
            'target' => '/srv/api-target',
        ]);
        $this->assertAccessDeniedJson($response);
    }
}
