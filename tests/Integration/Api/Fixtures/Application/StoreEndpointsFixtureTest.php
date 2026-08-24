<?php

namespace App\Tests\Integration\Api\Fixtures\Application;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use App\Tests\Traits\MocksUuids;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;

/**
 * Freezes the 201 envelope of the application API store endpoints, including the
 * meta.resource link the GET suite never touches. Every generated value is pinned:
 * uuids through the mocked Ramsey factory, Str::random through
 * createRandomStringsUsing, and the database password's special characters through
 * the shared random_int shim on the base class.
 */
#[Group('api-fixtures')]
class StoreEndpointsFixtureTest extends FixtureTestCase
{
    use MocksUuids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureAdmin();
    }

    protected function tearDown(): void
    {
        Str::createRandomStringsUsing();

        parent::tearDown();
    }

    public function test_store_user(): void
    {
        $response = $this->postJson('/api/application/users', [
            'email' => 'stored@fixture.test',
            'username' => 'fixture.stored',
            'password' => 'fixture-stored-password',
            'language' => 'en',
            'timezone' => 'UTC',
        ]);

        $this->assertFixtureSnapshot($response, 201);
    }

    public function test_store_node(): void
    {
        // The creating hook regenerates the uuid (mocked factory) and both daemon
        // tokens (pinned Str::random); only the uuid surfaces in the response.
        Str::createRandomStringsUsing(fn ($length) => str_repeat('a', $length));

        $response = $this->postJson('/api/application/nodes', [
            'name' => 'fixture-stored-node',
            'fqdn' => 'stored.fixture.test',
            'scheme' => 'https',
            'memory' => 2048,
            'memory_overallocate' => 0,
            'disk' => 20480,
            'disk_overallocate' => 0,
            'cpu' => 200,
            'cpu_overallocate' => 0,
            'daemon_sftp' => 2022,
            'daemon_listen' => 8080,
            'daemon_connect' => 8080,
            'upload_size' => 256,
            'tags' => ['fixture-store'],
        ]);

        $this->assertFixtureSnapshot($response, 201);
    }

    public function test_store_database_host(): void
    {
        // HostCreationService verifies credentials with buildConnection()->getPdo(),
        // so hand DB::build a stub connection instead of dialling 192.0.2.30.
        $this->fakeHostConnection();

        $response = $this->postJson('/api/application/database-hosts', [
            'name' => 'fixture-stored-host',
            'host' => '192.0.2.30',
            'port' => 3306,
            'username' => 'fixture-store',
            'password' => 'fixture-stored-host-password',
            'node_ids' => [100],
        ]);

        $this->assertFixtureSnapshot($response, 201);
    }

    public function test_store_role(): void
    {
        $response = $this->postJson('/api/application/roles', [
            'name' => 'Fixture Stored Role',
        ]);

        $this->assertFixtureSnapshot($response, 201);
    }

    public function test_store_mount(): void
    {
        // StoreMountRequest declares no rules, so validated() is always empty and the
        // controller's fill() inserts nothing but the uuid, which 500s on the NOT NULL
        // mounts.name column. There is no valid 201 response to freeze until that is fixed.
        $this->markTestSkipped('POST /api/application/mounts always returns a 500: StoreMountRequest has no rules, so the insert violates NOT NULL on mounts.name.');
    }

    public function test_import_egg(): void
    {
        // The controller reads the raw request body, and a pinned uuid in the file
        // keeps the importer away from its Uuid::uuid4() fallback.
        $yaml = <<<'YAML'
            meta:
              version: PLCN_v3
            name: Fixture Imported Egg
            author: import@fixture.test
            uuid: 00000000-0000-4000-8000-000000000700
            description: A pinned imported egg for the API test fixture suite.
            docker_images:
              'Java 21': 'ghcr.io/fixture/java:21'
            startup_commands:
              Default: 'java -jar server.jar'
            config:
              files: {}
              startup:
                done: 'Done'
              logs: {}
              stop: 'stop'
            scripts:
              installation:
                script: 'echo install'
                container: 'ghcr.io/fixture/installer:latest'
                entrypoint: 'ash'
            variables: []
            YAML;

        $response = $this->call(
            'POST',
            '/api/application/eggs/import',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->defaultHeaders),
            $yaml,
        );

        $this->assertFixtureSnapshot($response, 201);
    }

    public function test_store_server(): void
    {
        // The creation service pushes the new server to Wings over HTTP.
        Http::fake();

        $response = $this->postJson('/api/application/servers', [
            'external_id' => 'fixture-stored-ext',
            'name' => 'Fixture Stored Server',
            'description' => 'A pinned stored server for the API test fixture suite.',
            'user' => 101,
            'egg' => 100,
            'docker_image' => 'ghcr.io/fixture/java:21',
            'startup' => 'java -jar {{SERVER_JARFILE}}',
            'environment' => [
                'SERVER_JARFILE' => 'stored.jar',
                'HIDDEN_TOKEN' => 'internal',
            ],
            'limits' => [
                'memory' => 128,
                'swap' => 0,
                'disk' => 512,
                'io' => 500,
                'cpu' => 100,
            ],
            'feature_limits' => [
                'databases' => 1,
                'allocations' => 1,
                'backups' => 1,
            ],
            'allocation' => [
                'default' => 102,
            ],
        ]);

        $this->assertFixtureSnapshot($response, 201);
    }

    public function test_store_server_database(): void
    {
        $this->fakeHostConnection();
        Str::createRandomStringsUsing(fn ($length) => str_repeat('a', $length));
        self::$pinRandomInt = true;

        $response = $this->postJson('/api/application/servers/100/databases', [
            'database' => 'stored',
            'remote' => '%',
            'host' => 100,
        ]);

        $this->assertFixtureSnapshot($response, 201);
    }

    public function test_store_server_database_with_password_include(): void
    {
        // The generated password only surfaces through the password include.
        $this->fakeHostConnection();
        Str::createRandomStringsUsing(fn ($length) => str_repeat('a', $length));
        self::$pinRandomInt = true;

        $response = $this->postJson('/api/application/servers/100/databases?include=password', [
            'database' => 'storedpw',
            'remote' => '%',
            'host' => 100,
        ]);

        $this->assertFixtureSnapshot($response, 201);
    }

    /**
     * Every statement against a database host goes through DB::build, so intercepting
     * that keeps the real service and response shape while skipping the network.
     */
    private function fakeHostConnection(): void
    {
        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('statement')->andReturn(true);
        $connection->shouldReceive('getPdo')->andReturn(\Mockery::mock(\PDO::class));

        // A proxy mock forwards everything except build() to the real manager.
        $manager = \Mockery::mock(DB::getFacadeRoot());
        $manager->shouldReceive('build')->andReturn($connection);
        DB::swap($manager);
    }
}
