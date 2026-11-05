<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Not recorded yet, so tables left here are from an earlier run that failed halfway.
        Schema::dropIfExists('server_transfer_batch_items');
        Schema::dropIfExists('server_transfer_batches');

        Schema::create('server_transfer_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('old_node');
            $table->unsignedInteger('new_node');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('status')->index();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('old_node')->references('id')->on('nodes')->cascadeOnDelete();
            $table->foreign('new_node')->references('id')->on('nodes')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('server_transfer_batch_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('batch_id');
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('server_transfer_id')->nullable();
            $table->string('new_ip')->nullable();
            $table->boolean('move_backups')->default(true);
            $table->string('status');
            $table->boolean('was_running')->default(false);
            $table->text('error')->nullable();
            $table->timestamp('stop_requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->foreign('batch_id')->references('id')->on('server_transfer_batches')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('server_transfer_id')->references('id')->on('server_transfers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_transfer_batch_items');
        Schema::dropIfExists('server_transfer_batches');
    }
};
