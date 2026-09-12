<?php

namespace App\Tests\Integration\Api\Application;

use App\Data\Api\Application\ServerData;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Response;
use Mockery;
use Mockery\MockInterface;

class ServerControllerTest extends ApplicationApiIntegrationTestCase
{
    private MockInterface $daemonServerRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->daemonServerRepository = Mockery::mock(DaemonServerRepository::class);
        $this->swap(DaemonServerRepository::class, $this->daemonServerRepository);
    }

    /** @return array<string, mixed> */
    private function storePayload(): array
    {
        $node = Node::factory()->create();
        $allocation = Allocation::factory()->create(['node_id' => $node->id, 'server_id' => null]);
        // A factory egg has no variables, so no required environment values get in the way.
        $egg = Egg::factory()->create();

        return [
            'name' => 'api-server',
            'user' => $this->getApiUser()->id,
            'egg' => $egg->id,
            'docker_image' => 'ghcr.io/pelican-eggs/yolks:java_21',
            'startup' => 'java -jar test.jar',
            'environment' => [],
            'limits' => ['memory' => 0, 'swap' => 0, 'disk' => 0, 'io' => 500, 'cpu' => 0],
            'feature_limits' => ['databases' => 0, 'allocations' => 0, 'backups' => 0],
            'allocation' => ['default' => $allocation->id],
            'start_on_completion' => false,
        ];
    }

    public function test_list_all_servers(): void
    {
        $server = $this->createServerModel();

        $response = $this->getJson('/api/application/servers');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $server->id, 'uuid' => $server->uuid]);
    }

    public function test_return_single_server(): void
    {
        $server = $this->createServerModel();

        $response = $this->getJson('/api/application/servers/' . $server->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'object' => 'server',
            'attributes' => $this->getExpectedData(ServerData::class, $server),
        ], true);
    }

    public function test_return_server_by_external_id(): void
    {
        $server = $this->createServerModel(['external_id' => 'ext-123']);

        $response = $this->getJson('/api/application/servers/external/ext-123');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment(['id' => $server->id]);
    }

    public function test_create_server(): void
    {
        $this->daemonServerRepository->expects('setServer')->andReturnSelf();
        $this->daemonServerRepository->expects('create')->andReturnUndefined();

        $response = $this->postJson('/api/application/servers', $this->storePayload());

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('servers', ['name' => 'api-server']);
    }

    public function test_server_is_removed_when_daemon_creation_fails(): void
    {
        // The failed creation triggers a force delete, which talks to the daemon again.
        $this->daemonServerRepository->shouldReceive('setServer')->andReturnSelf();
        $this->daemonServerRepository->expects('create')->andThrow(new ConnectionException());
        $this->daemonServerRepository->expects('delete')->andReturnUndefined();

        $this->postJson('/api/application/servers', $this->storePayload())
            ->assertServerError();

        $this->assertDatabaseMissing('servers', ['name' => 'api-server']);
    }

    public function test_create_server_requires_limits(): void
    {
        $payload = $this->storePayload();
        unset($payload['limits']);

        $this->postJson('/api/application/servers', $payload)->assertUnprocessable();
    }

    public function test_delete_server(): void
    {
        $this->daemonServerRepository->expects('setServer')->andReturnSelf();
        $this->daemonServerRepository->expects('delete')->andReturnUndefined();

        $server = $this->createServerModel();

        $this->deleteJson('/api/application/servers/' . $server->id)
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }

    public function test_force_delete_server_survives_daemon_failure(): void
    {
        $this->daemonServerRepository->expects('setServer')->andReturnSelf();
        $this->daemonServerRepository->expects('delete')->andThrow(new ConnectionException());

        $server = $this->createServerModel();

        $this->deleteJson('/api/application/servers/' . $server->id . '/force')
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }

    public function test_error_returned_if_no_permission(): void
    {
        $server = $this->createServerModel();
        $this->createNewDefaultApiKey($this->getApiUser(), [Server::RESOURCE_NAME => AdminAcl::NONE]);

        $response = $this->getJson('/api/application/servers/' . $server->id);
        $this->assertAccessDeniedJson($response);
    }

    public function test_api_key_without_write_permissions_cannot_create(): void
    {
        $this->createNewDefaultApiKey($this->getApiUser(), [Server::RESOURCE_NAME => AdminAcl::READ]);

        $response = $this->postJson('/api/application/servers', $this->storePayload());
        $this->assertAccessDeniedJson($response);
    }
}
