<?php

namespace App\Tests\Integration\Migrations;

use App\Models\User;
use App\Tests\Integration\IntegrationTestCase;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;

class UpdateUsersTotpMigrationTest extends IntegrationTestCase
{
    public function test_pterodactyl_totp_secret_and_recovery_tokens_are_carried_over(): void
    {
        $this->recreatePterodactylSchema();

        $secret = (new Google2FA())->generateSecretKey();
        $enabled = User::factory()->create();
        $disabled = User::factory()->create();

        DB::table('users')->where('id', $enabled->id)->update(['use_totp' => true, 'totp_secret' => encrypt($secret, false)]);
        DB::table('users')->where('id', $disabled->id)->update(['use_totp' => false, 'totp_secret' => encrypt('stale', false)]);
        DB::table('recovery_tokens')->insert([
            ['user_id' => $enabled->id, 'token' => password_hash('recovery-one', PASSWORD_DEFAULT)],
            ['user_id' => $disabled->id, 'token' => password_hash('recovery-two', PASSWORD_DEFAULT)],
        ]);

        (require database_path('migrations/2025_07_22_091435_update_users_totp.php'))->up();

        $this->assertFalse(Schema::hasColumn('users', 'use_totp'));
        $this->assertFalse(Schema::hasTable('recovery_tokens'));

        $enabled->refresh();
        $this->assertSame($secret, $enabled->mfa_app_secret);

        $provider = AppAuthentication::make();
        $this->assertTrue($provider->verifyRecoveryCode('recovery-one', $enabled));

        $disabled->refresh();
        $this->assertNull($disabled->mfa_app_secret);
        $this->assertNull($disabled->mfa_app_recovery_codes);
    }

    public function test_rerun_after_partial_run_finishes_the_schema_changes(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_app_recovery_codes', 'mfa_email_enabled']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->text('totp_secret')->nullable();
            $table->timestamp('totp_authenticated_at')->nullable();
        });

        (require database_path('migrations/2025_07_22_091435_update_users_totp.php'))->up();

        $this->assertTrue(Schema::hasColumns('users', ['mfa_app_secret', 'mfa_app_recovery_codes', 'mfa_email_enabled']));
        $this->assertFalse(Schema::hasColumn('users', 'totp_secret'));
        $this->assertFalse(Schema::hasColumn('users', 'totp_authenticated_at'));
    }

    private function recreatePterodactylSchema(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('use_totp')->default(false);
            $table->text('totp_secret')->nullable();
            $table->timestamp('totp_authenticated_at')->nullable();
        });

        Schema::create('recovery_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
}
