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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->boolean('suspend_servers')->default(false);
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'lifted_at']);
        });

        Schema::create('user_suspension_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_suspension_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->text('error')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('unsuspended_at')->nullable();
            $table->timestamps();

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
