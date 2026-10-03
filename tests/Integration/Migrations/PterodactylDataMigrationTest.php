<?php

namespace App\Tests\Integration\Migrations;

use App\Models\ApiKey;
use App\Models\EggVariable;
use App\Models\Node;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PterodactylDataMigrationTest extends IntegrationTestCase
{
    public function test_localhost_nodes_get_their_daemon_connect_port_without_validation(): void
    {
        $node = Node::factory()->create(['behind_proxy' => false]);
        DB::table('nodes')->where('id', $node->id)->update(['fqdn' => 'localhost', 'daemon_listen' => 8443, 'daemon_connect' => 8080]);

        $this->runMigration('2025_07_06_213447_match-node-port');

        $this->assertDatabaseHas('nodes', ['id' => $node->id, 'daemon_connect' => 8443]);
    }

    public function test_passwords_are_removed_from_failed_login_logs(): void
    {
        $id = DB::table('activity_logs')->insertGetId([
            'event' => 'auth:fail',
            'ip' => '127.0.0.1',
            'timestamp' => now(),
            'properties' => json_encode(['user' => 'admin', 'password' => 'hunter2']),
        ]);

        $this->runMigration('2025_07_19_141511_clear_password_from_failed_auth_logs');

        $this->assertSame(['user' => 'admin'], json_decode(DB::table('activity_logs')->find($id)->properties, true));
    }

    public function test_duplicate_egg_variables_are_merged_without_losing_server_values(): void
    {
        $server = $this->createServerModel();
        $migration = require database_path('migrations/2025_04_01_033956_egg_variable_unique_foreign_key.php');

        // MySQL backs the egg_id foreign key with the unique index, so give it its own index first.
        Schema::table('egg_variables', function (Blueprint $table) {
            if (!Schema::hasIndex('egg_variables', ['egg_id'])) {
                $table->index('egg_id');
            }

            $table->dropUnique(['egg_id', 'env_variable']);
            $table->dropUnique(['egg_id', 'name']);
        });

        $keep = EggVariable::factory()->create(['egg_id' => $server->egg_id, 'env_variable' => 'FOO', 'name' => 'Foo']);
        $duplicate = EggVariable::factory()->create(['egg_id' => $server->egg_id, 'env_variable' => 'FOO', 'name' => 'Foo copy']);
        $shared = EggVariable::factory()->create(['egg_id' => $server->egg_id, 'env_variable' => 'BAR', 'name' => 'Shared']);
        $sharedName = EggVariable::factory()->create(['egg_id' => $server->egg_id, 'env_variable' => 'BAZ', 'name' => 'Shared']);
        EggVariable::factory()->create(['egg_id' => $server->egg_id, 'env_variable' => 'TAKEN', 'name' => 'Shared (BAZ)']);
        $long = EggVariable::factory()->create(['egg_id' => $server->egg_id, 'env_variable' => 'LONG_ONE', 'name' => str_repeat('a', 191)]);
        $longDuplicate = EggVariable::factory()->create(['egg_id' => $server->egg_id, 'env_variable' => 'LONG_TWO', 'name' => str_repeat('a', 191)]);

        $other = $this->createServerModel(['egg_id' => $server->egg_id]);
        DB::table('server_variables')->insert([
            ['server_id' => $server->id, 'variable_id' => $keep->id, 'variable_value' => 'kept'],
            ['server_id' => $server->id, 'variable_id' => $duplicate->id, 'variable_value' => 'dropped'],
            ['server_id' => $other->id, 'variable_id' => $duplicate->id, 'variable_value' => 'only value'],
        ]);

        $migration->up();

        $this->assertDatabaseMissing('egg_variables', ['id' => $duplicate->id]);
        $this->assertSame('kept', DB::table('server_variables')->where('server_id', $server->id)->where('variable_id', $keep->id)->value('variable_value'));
        $this->assertSame('only value', DB::table('server_variables')->where('server_id', $other->id)->where('variable_id', $keep->id)->value('variable_value'));
        $this->assertDatabaseMissing('server_variables', ['variable_id' => $duplicate->id]);
        $this->assertDatabaseHas('egg_variables', ['id' => $shared->id, 'name' => 'Shared']);
        $this->assertDatabaseHas('egg_variables', ['id' => $sharedName->id, 'name' => 'Shared (2)']);
        $this->assertDatabaseHas('egg_variables', ['id' => $long->id, 'name' => str_repeat('a', 191)]);
        $this->assertDatabaseHas('egg_variables', ['id' => $longDuplicate->id, 'name' => str_repeat('a', 180) . ' (LONG_TWO)']);
    }

    public function test_api_key_and_webhook_migrations_can_rerun_on_a_migrated_schema(): void
    {
        ApiKey::factory()->create(['user_id' => $this->createServerModel()->owner_id, 'permissions' => [Node::RESOURCE_NAME => 3]]);
        $permissions = DB::table('api_keys')->pluck('permissions', 'id');
        $this->assertNotEmpty($permissions);

        $this->runMigration('2024_11_04_185326_revamp_api_keys_permissions');
        $this->runMigration('2025_10_28_152500_rename_description_webhooks');

        $this->assertEquals($permissions, DB::table('api_keys')->pluck('permissions', 'id'));
    }

    private function runMigration(string $name): void
    {
        (require database_path("migrations/$name.php"))->up();
    }
}
