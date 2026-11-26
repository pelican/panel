<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as OAuthUser;
use SocialiteProviders\Authentik\Provider as AuthentikProvider;
use SocialiteProviders\Discord\Provider as DiscordProvider;

/**
 * @param  array<string, mixed>  $raw
 */
function fakeOAuthUser(string $driver, string $providerClass, array $raw): void
{
    $oauthUser = (new OAuthUser())->setRaw($raw)->map([
        'id' => $raw['id'],
        'nickname' => 'oauth-user',
        'email' => $raw['email'],
        ...array_intersect_key($raw, ['email_verified' => true]),
    ]);

    $provider = Mockery::mock($providerClass);
    $provider->shouldReceive('user')->andReturn($oauthUser);

    Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);
}

function enableOAuthAutoLink(string $driver): void
{
    $id = strtoupper($driver);

    config()->set("extensions.OAUTH_{$id}_ENABLED", true);
    config()->set("extensions.OAUTH_{$id}_SHOULD_LINK_MISSING_USERS", true);
}

it('does not link a user by an email the provider reports as unverified', function (mixed $verified) {
    enableOAuthAutoLink('authentik');
    /** @var User $user */
    $user = User::factory()->create(['email' => 'victim@example.com']);
    fakeOAuthUser('authentik', AuthentikProvider::class, ['id' => 'attacker', 'email' => 'victim@example.com', 'email_verified' => $verified]);

    $this->get('/auth/oauth/callback/authentik')->assertRedirect(route('auth.login'));

    $this->assertGuest();
    expect($user->refresh()->oauth)->not->toHaveKey('authentik');
})->with([false, 'false', 0]);

it('links a user by an email the provider reports as verified', function () {
    enableOAuthAutoLink('authentik');
    /** @var User $user */
    $user = User::factory()->create(['email' => 'user@example.com']);
    fakeOAuthUser('authentik', AuthentikProvider::class, ['id' => 'abc', 'email' => 'user@example.com', 'email_verified' => true]);

    $this->get('/auth/oauth/callback/authentik')->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->oauth)->toHaveKey('authentik', 'abc');
});

it('does not link a user by an unverified discord email', function (array $raw) {
    enableOAuthAutoLink('discord');
    /** @var User $user */
    $user = User::factory()->create(['email' => 'victim@example.com']);
    fakeOAuthUser('discord', DiscordProvider::class, ['id' => '123', 'email' => 'victim@example.com', ...$raw]);

    $this->get('/auth/oauth/callback/discord')->assertRedirect(route('auth.login'));

    $this->assertGuest();
    expect($user->refresh()->oauth)->not->toHaveKey('discord');
})->with([
    'unverified' => [['verified' => false]],
    'flag missing' => [[]],
]);

it('links a user by a verified discord email', function () {
    enableOAuthAutoLink('discord');
    /** @var User $user */
    $user = User::factory()->create(['email' => 'user@example.com']);
    fakeOAuthUser('discord', DiscordProvider::class, ['id' => '123', 'email' => 'user@example.com', 'verified' => true]);

    $this->get('/auth/oauth/callback/discord')->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->oauth)->toHaveKey('discord', '123');
});
