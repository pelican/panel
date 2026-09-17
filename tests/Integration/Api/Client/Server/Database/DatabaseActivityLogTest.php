<?php

namespace App\Tests\Integration\Api\Client\Server\Database;

use App\Models\ActivityLog;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class DatabaseActivityLogTest extends ClientApiIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseHost::buildConnection reaches the external host through DB::build, so intercept
        // only that call and keep every other DB method real.
        $connection = \Mockery::mock(Connection::class);
        $connection->allows('statement')->andReturnTrue();

        $manager = \Mockery::mock(DB::getFacadeRoot());
        $manager->allows('build')->andReturns($connection);
        DB::swap($manager);
    }

    /**
     * The service logs create and delete itself, so the controller must not log again: doing so
     * through the shared Activity facade used to clobber the pending entry and fail on the NOT NULL
     * event column for create, and wrote a duplicate row for delete.
     */
    public function test_creating_and_deleting_a_database_logs_exactly_one_activity_each(): void
    {
        [$user, $server] = $this->generateTestAccount();
        DatabaseHost::factory()->create();

        $this->actingAs($user)->postJson($this->link($server, '/databases'), [
            'database' => 'regression',
            'remote' => '%',
        ])->assertOk();

        $database = Database::query()->where('server_id', $server->id)->firstOrFail();

        $this->assertSame(1, ActivityLog::query()->where('event', 'server:database.create')->count());
        $this->assertSame($database->database, ActivityLog::query()->where('event', 'server:database.create')->first()->properties->get('name'));

        $this->actingAs($user)->deleteJson($this->link($server, '/databases/' . $database->id))->assertNoContent();

        $this->assertDatabaseMissing('databases', ['id' => $database->id]);
        $this->assertSame(1, ActivityLog::query()->where('event', 'server:database.delete')->count());
        $this->assertSame(2, ActivityLog::query()->count());
    }
}
