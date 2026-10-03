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
        // Each added column is its own statement on MySQL, so check them one by one to survive a partial run.
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mfa_app_secret')) {
                $table->text('mfa_app_secret')->nullable();
            }
            if (!Schema::hasColumn('users', 'mfa_app_recovery_codes')) {
                $table->text('mfa_app_recovery_codes')->nullable();
            }
            if (!Schema::hasColumn('users', 'mfa_email_enabled')) {
                $table->boolean('mfa_email_enabled')->default(false);
            }
        });

        if (Schema::hasColumn('users', 'use_totp')) {
            // Both secrets are base32 strings encrypted without serialization, so they can be copied as is.
            DB::table('users')
                ->where('use_totp', true)
                ->whereNotNull('totp_secret')
                ->update(['mfa_app_secret' => DB::raw('totp_secret')]);

            // Recovery tokens are bcrypt hashes, which is the same format Filament stores recovery codes in.
            if (Schema::hasTable('recovery_tokens')) {
                DB::table('users')
                    ->where('use_totp', true)
                    ->whereNotNull('totp_secret')
                    ->select('id')
                    ->lazyById()
                    ->each(function ($user) {
                        $tokens = DB::table('recovery_tokens')->where('user_id', $user->id)->pluck('token');

                        if ($tokens->isNotEmpty()) {
                            DB::table('users')
                                ->where('id', $user->id)
                                ->update(['mfa_app_recovery_codes' => Crypt::encryptString(json_encode($tokens->all()))]);
                        }
                    });
            }
        }

        $legacyColumns = array_values(array_filter(
            ['use_totp', 'totp_secret', 'totp_authenticated_at'],
            fn ($column) => Schema::hasColumn('users', $column),
        ));

        if ($legacyColumns) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($legacyColumns));
        }

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
