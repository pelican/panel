<?php

namespace App\Tests\Integration\Api\Application;

use App\Data\Api\Application\DatabaseHostData;
use App\Models\DatabaseHost;
use App\Services\Acl\Api\AdminAcl;
use Exception;
use Illuminate\Database\Connection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDO;
use PDOException;

class DatabaseHostControllerTest extends ApplicationApiIntegrationTestCase
{
    /**
     * The host services confirm access via DatabaseHost->buildConnection()->getPdo(),
     * so fake the remote connection while leaving the panel's own connection real.
     */
    private function fakeRemoteDatabaseConnection(?Exception $exception = null): void
    {
        $connection = Mockery::mock(Connection::class);

        if ($exception) {
            $connection->shouldReceive('getPdo')->andThrow($exception);
        } else {
            $connection->shouldReceive('getPdo')->andReturn(Mockery::mock(PDO::class));
        }

        $manager = Mockery::mock(app('db'));
        $manager->shouldReceive('build')->andReturn($connection);
        DB::swap($manager);
        $this->app->instance('db', $manager);
    }

    public function test_list_all_database_hosts(): void
    {
        $host = DatabaseHost::factory()->create();

        $response = $this->getJson('/api/application/database-hosts');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $host->id]);
    }

    public function test_return_single_database_host(): void
    {
        $host = DatabaseHost::factory()->create();

        $response = $this->getJson('/api/application/database-hosts/' . $host->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'object' => 'database_host',
            'attributes' => $this->getExpectedData(DatabaseHostData::class, $host),
        ], true);
    }

    public function test_create_database_host(): void
    {
        $this->fakeRemoteDatabaseConnection();

        $response = $this->postJson('/api/application/database-hosts', [
            'name' => 'api-db-host',
            'host' => '127.0.0.1',
            'port' => 3306,
            'username' => 'pelicanuser',
            'password' => 'secret1234',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $this->assertDatabaseHas('database_hosts', ['name' => 'api-db-host', 'host' => '127.0.0.1']);
    }

    public function test_database_host_is_not_saved_when_connection_fails(): void
    {
        $this->fakeRemoteDatabaseConnection(new PDOException('Connection refused'));

        $this->postJson('/api/application/database-hosts', [
            'name' => 'unreachable-host',
            'host' => '10.0.0.99',
            'port' => 3306,
            'username' => 'pelicanuser',
            'password' => 'secret1234',
        ])->assertServerError();

        $this->assertDatabaseMissing('database_hosts', ['name' => 'unreachable-host']);
    }

    public function test_create_database_host_requires_host(): void
    {
        $this->postJson('/api/application/database-hosts', [
            'name' => 'api-db-host',
            'port' => 3306,
            'username' => 'pelicanuser',
        ])->assertUnprocessable();
    }

    public function test_update_database_host(): void
    {
        $this->fakeRemoteDatabaseConnection();

        $host = DatabaseHost::factory()->create();

        $response = $this->patchJson('/api/application/database-hosts/' . $host->id, [
            'name' => 'renamed-db-host',
            'host' => $host->host,
            'port' => $host->port,
            'username' => $host->username,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('database_hosts', ['id' => $host->id, 'name' => 'renamed-db-host']);
    }

    public function test_delete_database_host(): void
    {
        $host = DatabaseHost::factory()->create();

        $this->deleteJson('/api/application/database-hosts/' . $host->id)
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertDatabaseMissing('database_hosts', ['id' => $host->id]);
    }

    public function test_error_returned_if_no_permission(): void
    {
        $host = DatabaseHost::factory()->create();
        $this->createNewDefaultApiKey($this->getApiUser(), [DatabaseHost::RESOURCE_NAME => AdminAcl::NONE]);

        $response = $this->getJson('/api/application/database-hosts/' . $host->id);
        $this->assertAccessDeniedJson($response);
    }

    public function test_api_key_without_write_permissions_cannot_create(): void
    {
        $this->createNewDefaultApiKey($this->getApiUser(), [DatabaseHost::RESOURCE_NAME => AdminAcl::READ]);

        $response = $this->postJson('/api/application/database-hosts', [
            'name' => 'api-db-host',
            'host' => '127.0.0.1',
            'port' => 3306,
            'username' => 'pelicanuser',
        ]);
        $this->assertAccessDeniedJson($response);
    }
}
