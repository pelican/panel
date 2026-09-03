<?php

namespace App\Tests\Integration\Http\Middleware;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\ApiKey;
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
            ->assertJsonPath('errors.0.detail', trans('auth.account_suspended_contact', ['email' => 'support@example.com']));
    }

    public function test_suspended_api_tokens_are_rejected_without_a_json_accept_header(): void
    {
        $user = User::factory()->create(['suspended_at' => now()]);
        $key = ApiKey::factory()->for($user)->create(['key_type' => ApiKey::TYPE_ACCOUNT]);

        $response = $this
            ->withHeader('Accept', 'text/html')
            ->withHeader('Authorization', 'Bearer ' . $key->identifier . $key->token)
            ->get('/api/client/account');

        $response->assertForbidden();
        $this->assertFalse($response->isRedirect());
    }

    public function test_old_browser_sessions_are_invalidated_after_the_remember_token_changes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                EnsureAccountIsActive::SESSION_KEY => hash('sha256', (string) $user->getRememberToken()),
            ]);

        $user->forceFill(['remember_token' => str_repeat('a', 60)])->save();

        $this->get('/')
            ->assertRedirect(route('filament.app.auth.login'));

        $this->assertGuest();
    }

    public function test_legacy_browser_sessions_are_invalidated_with_a_non_database_session_driver(): void
    {
        config()->set('session.driver', 'array');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth_session_version' => 1]);

        $this->get('/')
            ->assertRedirect(route('filament.app.auth.login'));

        $this->assertGuest();
    }
}
