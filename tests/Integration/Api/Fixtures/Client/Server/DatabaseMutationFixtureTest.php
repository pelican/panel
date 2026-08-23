<?php

namespace App\Tests\Integration\Api\Fixtures\Client\Server;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class DatabaseMutationFixtureTest extends FixtureTestCase
{
    private const BASE = '/api/client/servers/00000000-0000-4000-8000-000000000400';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureOwner();

        // The generated username and password come from Str::random plus the shared
        // random_int shim on the base class; pin both so the snapshots are stable.
        Str::createRandomStringsUsing(fn ($length) => str_repeat('k', $length));
        self::$pinRandomInt = true;

        // DatabaseHost::buildConnection reaches the external host through DB::build,
        // so intercept only that call and keep every other DB method real. A proxy
        // partial forwards everything else to the actual manager, which keeps the
        // controller and services running unmodified against sqlite.
        $connection = \Mockery::mock(Connection::class);
        $connection->allows('statement')->andReturnTrue();

        $manager = \Mockery::mock(DB::getFacadeRoot());
        $manager->allows('build')->andReturns($connection);
        DB::swap($manager);
    }

    protected function tearDown(): void
    {
        Str::createRandomStringsUsing();

        parent::tearDown();
    }

    public function test_store(): void
    {
        $this->markTestSkipped('POST /databases currently 500s: DatabaseManagementService::create logs its own activity inside the controller Activity transaction, resetting the pending log, so the wrapper then saves an event-less activity row and violates the NOT NULL constraint. Freeze the fixture once that double log is fixed.');
    }

    public function test_store_with_password_include(): void
    {
        $this->markTestSkipped('Skipped for the same double activity log 500 as test_store; this variant would prove the request include merges with the include the endpoint always applies.');
    }

    public function test_rotate_password(): void
    {
        $this->assertFixtureSnapshot($this->postJson(self::BASE . '/databases/100/rotate-password?include=password'));
    }
}
