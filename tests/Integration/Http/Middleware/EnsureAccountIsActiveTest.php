<?php

namespace App\Tests\Integration\Http\Middleware;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use App\Tests\Integration\IntegrationTestCase;

class EnsureAccountIsActiveTest extends IntegrationTestCase
{
    public function test_suspended_accounts_are_rejected_from_the_client_api(): void
    {
        config()->set('mail.from.address', 'support@example.com');
        $user = User::factory()->create(['suspended_at' => now()]);

        $this->actingAs($user)
            ->getJson('/api/client/account')
            ->assertForbidden()
            ->assertJsonPath('errors.0.detail', 'Your account has been suspended. Please contact support@example.com for assistance.');
    }

    public function test_old_browser_sessions_are_invalidated_after_the_version_changes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([EnsureAccountIsActive::SESSION_KEY => $user->auth_session_version]);

        $user->forceFill(['auth_session_version' => $user->auth_session_version + 1])->save();

        $this->get('/')
            ->assertRedirect(route('filament.app.auth.login'));

        $this->assertGuest();
    }
}
