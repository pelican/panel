<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('remember_token')->index();
            $table->unsignedInteger('auth_session_version')->default(0)->after('suspended_at');
        });

        Schema::create('user_suspensions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('actor_id')->nullable();
            $table->text('reason');
            $table->boolean('suspend_servers')->default(false);
            $table->timestamp('lifted_at')->nullable();
            $table->unsignedInteger('lifted_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('lifted_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'lifted_at']);
        });

        Schema::create('user_suspension_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_suspension_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('server_id');
            $table->string('status', 32);
            $table->text('error')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('unsuspended_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->unique(['user_suspension_id', 'server_id']);
            $table->index(['user_suspension_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_suspension_servers');
        Schema::dropIfExists('user_suspensions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['suspended_at']);
            $table->dropColumn(['suspended_at', 'auth_session_version']);
        });
    }
};
