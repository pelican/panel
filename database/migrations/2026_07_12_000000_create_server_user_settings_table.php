<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_user_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('server_id');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->unique(['user_id', 'server_id']);
        });

        // Backup notifications now default off for everyone. Owners on existing
        // installs previously always received them, so seed an opt-in row for each
        // server's owner unless the panel-wide email switch was already disabled.
        if (!filter_var(env('PANEL_SEND_BACKUP_COMPLETED_NOTIFICATION', true), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $now = now();

        DB::table('servers')->select(['id', 'owner_id'])->orderBy('id')->chunkById(250, function ($servers) use ($now) {
            DB::table('server_user_settings')->insert($servers->map(fn ($server) => [
                'user_id' => $server->owner_id,
                'server_id' => $server->id,
                'settings' => json_encode(['manual_backup_notifications' => true, 'scheduled_backup_notifications' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_user_settings');
    }
};
