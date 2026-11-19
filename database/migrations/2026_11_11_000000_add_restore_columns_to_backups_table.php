<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backups can now outlive their server so that it can be restored later, so
     * they keep the server uuid and node they were made on.
     */
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            if (!Schema::hasColumn('backups', 'server_uuid')) {
                $table->char('server_uuid', 36)->nullable()->after('server_id');
            }
            if (!Schema::hasColumn('backups', 'node_id')) {
                $table->unsignedInteger('node_id')->nullable()->after('server_uuid');
            }
            if (!Schema::hasColumn('backups', 'pelican_config')) {
                $table->json('pelican_config')->nullable()->after('ignored_files');
            }
        });

        DB::table('backups')
            ->whereNull('server_uuid')
            ->update([
                'server_uuid' => DB::table('servers')->select('uuid')->whereColumn('servers.id', 'backups.server_id'),
                'node_id' => DB::table('servers')->select('node_id')->whereColumn('servers.id', 'backups.server_id'),
            ]);

        Schema::table('backups', function (Blueprint $table) {
            if (in_array(['server_id'], array_column(Schema::getForeignKeys('backups'), 'columns'))) {
                $table->dropForeign(['server_id']);
            }
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->unsignedInteger('server_id')->nullable()->change();
        });

        Schema::table('backups', function (Blueprint $table) {
            // Backups are still deleted with their server. ServerDeletionService detaches
            // the ones it keeps first.
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();

            if (!in_array(['node_id'], array_column(Schema::getForeignKeys('backups'), 'columns'))) {
                $table->foreign('node_id')->references('id')->on('nodes')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        DB::table('backups')->whereNull('server_id')->delete();

        Schema::table('backups', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
            $table->dropForeign(['node_id']);
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->unsignedInteger('server_id')->nullable(false)->change();
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->dropColumn(['server_uuid', 'node_id', 'pelican_config']);
        });
    }
};
