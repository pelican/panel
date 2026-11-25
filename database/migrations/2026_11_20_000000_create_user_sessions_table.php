<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The create runs as several statements on MySQL, so a failed run can leave a partial table behind.
        Schema::dropIfExists('user_sessions');

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            // The Laravel session id currently attached to this device, used to destroy it on revoke.
            $table->string('session_id', 191)->nullable();
            // sha256 of the random token stored in the device cookie.
            $table->char('token_hash', 64)->unique();
            $table->string('name', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('remember')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_active_at')->nullable()->index();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
