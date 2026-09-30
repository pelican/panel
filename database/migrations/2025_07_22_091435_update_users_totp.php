<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'mfa_app_secret')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('mfa_app_secret')->nullable();
                $table->text('mfa_app_recovery_codes')->nullable();
                $table->boolean('mfa_email_enabled')->default(false);
            });
        }

        if (!Schema::hasColumn('users', 'use_totp')) {
            Schema::dropIfExists('recovery_tokens');

            return;
        }

        // Both secrets are base32 strings encrypted without serialization, so they can be copied as is.
        DB::table('users')
            ->where('use_totp', true)
            ->whereNotNull('totp_secret')
            ->update(['mfa_app_secret' => DB::raw('totp_secret')]);

        // Recovery tokens are bcrypt hashes, which is the same format Filament stores recovery codes in.
        if (Schema::hasTable('recovery_tokens')) {
            DB::table('recovery_tokens')
                ->join('users', 'users.id', '=', 'recovery_tokens.user_id')
                ->where('users.use_totp', true)
                ->whereNotNull('users.totp_secret')
                ->get(['recovery_tokens.user_id', 'recovery_tokens.token'])
                ->groupBy('user_id')
                ->each(fn ($tokens, $userId) => DB::table('users')
                    ->where('id', $userId)
                    ->update(['mfa_app_recovery_codes' => Crypt::encryptString(json_encode($tokens->pluck('token')->all()))]));
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('use_totp');
            $table->dropColumn('totp_secret');
            $table->dropColumn('totp_authenticated_at');
        });

        Schema::dropIfExists('recovery_tokens');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not needed
    }
};
