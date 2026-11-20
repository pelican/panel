<?php

namespace App\Tests\Integration\Migrations;

use App\Models\Database;
use App\Models\DatabaseHost;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

class EscapeDatabaseGrantPatternsMigrationTest extends IntegrationTestCase
{
    public function test_existing_grants_are_reissued_with_escaped_names_and_unreachable_hosts_are_skipped(): void
    {
        $server = $this->createServerModel();
        $goodHost = DatabaseHost::factory()->recycle($server->node)->create(['host' => '10.0.0.1']);
        $badHost = DatabaseHost::factory()->recycle($server->node)->create(['host' => '10.0.0.2']);

        $first = Database::factory()->create(['server_id' => $server->id, 'database_host_id' => $badHost->id, 'database' => "s{$server->id}_unreachable"]);
        $second = Database::factory()->create(['server_id' => $server->id, 'database_host_id' => $goodHost->id, 'database' => "s{$server->id}_my_db"]);

        $statements = [];
        $good = Mockery::mock(Connection::class);
        $good->allows('select')->andReturns([]);
        $good->allows('statement')->andReturnUsing(function (string $query) use (&$statements) {
            $statements[] = $query;

            // The legacy grant may already be gone (e.g. after a password rotation).
            if (str_starts_with($query, 'REVOKE')) {
                $pdo = new \PDOException('There is no such grant defined');
                $pdo->errorInfo = ['42000', 1141, 'There is no such grant defined'];

                throw new QueryException('remote', $query, [], $pdo);
            }

            return true;
        });

        $bad = Mockery::mock(Connection::class);
        $bad->allows('select')->andThrow(new \RuntimeException('Connection refused'));
        $bad->allows('statement')->andThrow(new \RuntimeException('Connection refused'));

        $manager = Mockery::mock(DB::getFacadeRoot());
        $manager->allows('build')->andReturnUsing(fn (array $config) => $config['host'] === $badHost->host ? $bad : $good);
        DB::swap($manager);

        Log::spy();

        (require database_path('migrations/2026_11_20_000000_escape_database_grant_patterns.php'))->up();

        $this->assertSame("REVOKE ALL PRIVILEGES ON `{$second->database}`.* FROM `{$second->username}`@`{$second->remote}`", $statements[0]);
        $this->assertStringContainsString("ON `s{$server->id}\\_my\\_db`.* TO `{$second->username}`@`{$second->remote}`", $statements[1]);
        $this->assertSame('FLUSH PRIVILEGES', $statements[2]);
        $this->assertCount(3, $statements);

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context['database_id'] === $first->id);
    }
}
