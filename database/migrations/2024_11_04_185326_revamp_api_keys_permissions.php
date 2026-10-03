<?php

use App\Models\Allocation;
use App\Models\ApiKey;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Egg;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('api_keys', 'permissions')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->json('permissions')->nullable();
            });
        }

        // The legacy columns are dropped in one statement, so if one is gone the permissions were already converted.
        if (!Schema::hasColumn('api_keys', 'r_servers')) {
            return;
        }

        foreach (ApiKey::all() as $apiKey) {
            $permissions = [
                Server::RESOURCE_NAME => intval($apiKey->r_servers ?? 0),
                Node::RESOURCE_NAME => intval($apiKey->r_nodes ?? 0),
                Allocation::RESOURCE_NAME => intval($apiKey->r_allocations ?? 0),
                User::RESOURCE_NAME => intval($apiKey->r_users ?? 0),
                Egg::RESOURCE_NAME => intval($apiKey->r_eggs ?? 0),
                DatabaseHost::RESOURCE_NAME => intval($apiKey->r_database_hosts ?? 0),
                Database::RESOURCE_NAME => intval($apiKey->r_server_databases ?? 0),
                Mount::RESOURCE_NAME => intval($apiKey->r_mounts ?? 0),
            ];

            DB::table('api_keys')
                ->where('id', $apiKey->id)
                ->update(['permissions' => $permissions]);
        }

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn([
                'r_servers',
                'r_nodes',
                'r_allocations',
                'r_users',
                'r_eggs',
                'r_database_hosts',
                'r_server_databases',
                'r_mounts',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->unsignedTinyInteger('r_servers')->default(0);
            $table->unsignedTinyInteger('r_nodes')->default(0);
            $table->unsignedTinyInteger('r_allocations')->default(0);
            $table->unsignedTinyInteger('r_users')->default(0);
            $table->unsignedTinyInteger('r_eggs')->default(0);
            $table->unsignedTinyInteger('r_database_hosts')->default(0);
            $table->unsignedTinyInteger('r_server_databases')->default(0);
            $table->unsignedTinyInteger('r_mounts')->default(0);
        });

        foreach (ApiKey::all() as $apiKey) {
            DB::table('api_keys')
                ->where('id', $apiKey->id)
                ->update([
                    'r_servers' => $apiKey->permissions[Server::RESOURCE_NAME],
                    'r_nodes' => $apiKey->permissions[Node::RESOURCE_NAME],
                    'r_allocations' => $apiKey->permissions[Allocation::RESOURCE_NAME],
                    'r_users' => $apiKey->permissions[User::RESOURCE_NAME],
                    'r_eggs' => $apiKey->permissions[Egg::RESOURCE_NAME],
                    'r_database_hosts' => $apiKey->permissions[DatabaseHost::RESOURCE_NAME],
                    'r_server_databases' => $apiKey->permissions[Database::RESOURCE_NAME],
                    'r_mounts' => $apiKey->permissions[Mount::RESOURCE_NAME],
                ]);
        }

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
