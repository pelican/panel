<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('server_restores')) {
            return;
        }

        Schema::create('server_restores', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedInteger('server_id');
            $table->unsignedBigInteger('backup_id')->nullable();
            $table->unsignedInteger('backup_host_id')->nullable();
            $table->text('source_key')->nullable();
            $table->text('source_url')->nullable();
            $table->string('status')->default('pending');
            // The uuid the node reports the restore's result with.
            $table->char('callback_uuid', 36)->nullable()->index();
            $table->boolean('start_on_completion')->default(false);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('backup_id')->references('id')->on('backups')->nullOnDelete();
            $table->foreign('backup_host_id')->references('id')->on('backup_hosts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_restores');
    }
};
