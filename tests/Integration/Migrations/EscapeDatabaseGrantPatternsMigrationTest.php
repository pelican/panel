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
        $good = $this->recordingConnection($statements, function (string $query) {
            // The legacy grant may already be gone (e.g. after a password rotation).
            if (str_starts_with($query, 'REVOKE')) {
                throw $this->queryException($query, 1141, 'There is no such grant defined');
            }
        });

        $bad = Mockery::mock(Connection::class);
        $bad->allows('select')->andThrow(new \RuntimeException('Connection refused'));
        $bad->allows('statement')->andThrow(new \RuntimeException('Connection refused'));

        $this->swapConnections(fn (array $config) => $config['host'] === $badHost->host ? $bad : $good);

        Log::spy();

        $this->runMigration();

        // The escaped grant must be in place before the legacy one is revoked.
        $this->assertStringContainsString("ON `s{$server->id}\\_my\\_db`.* TO `{$second->username}`@`{$second->remote}`", $statements[0]);
        $this->assertSame("REVOKE ALL PRIVILEGES ON `{$second->database}`.* FROM `{$second->username}`@`{$second->remote}`", $statements[1]);
        $this->assertSame('FLUSH PRIVILEGES', $statements[2]);
        $this->assertCount(3, $statements);

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context['database_id'] === $first->id);
    }

    /**
     * REVOKE commits implicitly, so if it fails after the new grant was issued the user must still
     * hold the escaped grant rather than being left without access.
     */
    public function test_failure_after_the_escaped_grant_is_issued_leaves_the_user_with_access(): void
    {
        $server = $this->createServerModel();
        $host = DatabaseHost::factory()->recycle($server->node)->create();
        $database = Database::factory()->create(['server_id' => $server->id, 'database_host_id' => $host->id, 'database' => "s{$server->id}_my_db"]);

        $statements = [];
        $connection = $this->recordingConnection($statements, function (string $query) {
            if (str_starts_with($query, 'REVOKE')) {
                throw $this->queryException($query, 2013, 'Lost connection to MySQL server during query');
            }
        });
        $this->swapConnections(fn () => $connection);

        Log::spy();

        $this->runMigration();

        $this->assertStringStartsWith('GRANT ', $statements[0]);
        $this->assertStringContainsString("ON `s{$server->id}\\_my\\_db`.* TO", $statements[0]);
        $this->assertStringStartsWith('REVOKE ', $statements[1]);
        $this->assertCount(2, $statements);

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context['database_id'] === $database->id);
    }

    /**
     * With partial_revokes enabled the grant is not escaped, so the "legacy" grant is the current
     * one and revoking it would lock the owner out.
     */
    public function test_grant_is_not_revoked_when_partial_revokes_is_enabled(): void
    {
        $server = $this->createServerModel();
        $host = DatabaseHost::factory()->recycle($server->node)->create();
        Database::factory()->create(['server_id' => $server->id, 'database_host_id' => $host->id, 'database' => "s{$server->id}_my_db"]);

        $statements = [];
        $connection = $this->recordingConnection($statements, partialRevokes: [(object) ['Variable_name' => 'partial_revokes', 'Value' => 'ON']]);
        $this->swapConnections(fn () => $connection);

        $this->runMigration();

        $this->assertStringContainsString("ON `s{$server->id}_my_db`.* TO", $statements[0]);
        $this->assertSame('FLUSH PRIVILEGES', $statements[1]);
        $this->assertCount(2, $statements);
    }

    /**
     * A name that exceeds MySQL's 64 character limit once escaped cannot be granted safely. Its
     * existing grant is left untouched and the admin is told why.
     */
    public function test_names_too_long_once_escaped_keep_their_existing_grant(): void
    {
        $server = $this->createServerModel();
        $host = DatabaseHost::factory()->recycle($server->node)->create();
        $database = Database::factory()->create(['server_id' => $server->id, 'database_host_id' => $host->id, 'database' => "s{$server->id}_" . str_repeat('_', 40)]);

        $statements = [];
        $connection = $this->recordingConnection($statements);
        $this->swapConnections(fn () => $connection);

        Log::spy();

        $this->runMigration();

        $this->assertCount(0, $statements);

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context['database_id'] === $database->id && str_contains($message, 'too long'));
    }

    /**
     * @param  array<int, string>  $statements
     * @param  array<int, object>  $partialRevokes
     */
    private function recordingConnection(array &$statements, ?\Closure $onStatement = null, array $partialRevokes = []): Connection
    {
        $connection = Mockery::mock(Connection::class);
        $connection->allows('select')->andReturns($partialRevokes);
        $connection->allows('statement')->andReturnUsing(function (string $query) use (&$statements, $onStatement) {
            $statements[] = $query;

            if ($onStatement) {
                $onStatement($query);
            }

            return true;
        });

        return $connection;
    }

    private function swapConnections(\Closure $resolver): void
    {
        $manager = Mockery::mock(DB::getFacadeRoot());
        $manager->allows('build')->andReturnUsing($resolver);
        DB::swap($manager);
    }

    private function queryException(string $query, int $code, string $message): QueryException
    {
        $pdo = new \PDOException($message);
        $pdo->errorInfo = ['HY000', $code, $message];

        return new QueryException('remote', $query, [], $pdo);
    }

    private function runMigration(): void
    {
        (require database_path('migrations/2026_11_20_000000_escape_database_grant_patterns.php'))->up();
    }
}
