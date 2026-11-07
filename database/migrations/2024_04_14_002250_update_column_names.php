<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Each step checks whether it already ran, so a migration that failed halfway can be run again.
        if (Schema::hasColumn('nodes', 'daemonBase')) {
            Schema::table('nodes', function (Blueprint $table) {
                $table->string('daemonBase', 191)->default(null)->change();
            });
        }

        foreach (['nodes' => ['daemonListen' => 'daemon_listen', 'daemonBase' => 'daemon_base', 'daemonSFTP' => 'daemon_sftp'], 'servers' => ['uuidShort' => 'uuid_short']] as $tableName => $columns) {
            foreach ($columns as $from => $to) {
                if (Schema::hasColumn($tableName, $from)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->renameColumn($from, $to));
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->renameColumn('daemon_listen', 'daemonListen');
            $table->renameColumn('daemon_sftp', 'daemonSFTP');
            $table->renameColumn('daemon_base', 'daemonBase');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->renameColumn('uuid_short', 'uuidShort');
        });
    }
};
