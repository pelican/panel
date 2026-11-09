<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as OAuthUser;
use SocialiteProviders\Steam\Provider;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $_SERVER['OAUTH_STEAM_ENABLED'] = 'true';
});

afterEach(function () {
    unset($_SERVER['OAUTH_STEAM_ENABLED']);
});

function fakeSteamUser(string $id): void
{
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn((new OAuthUser())->map(['id' => $id, 'nickname' => 'steam-user']));

    Socialite::shouldReceive('driver')->with('steam')->andReturn($provider);
}

it('does not link an oauth account the user did not start linking', function () {
    /** @var User $user */
    $user = User::factory()->create();
    fakeSteamUser('76561190000000001');

    $this->actingAs($user)
        ->get('/auth/oauth/callback/steam')
        ->assertRedirect(EditProfile::getUrl(['tab' => 'oauth::data::tab'], panel: 'app'));

    expect($user->refresh()->oauth)->not->toHaveKey('steam');

    // Signing in with that Steam account must not log in as the user either.
    auth()->guard()->logout();
    $this->get('/auth/oauth/callback/steam');

    $this->assertGuest();
});

it('links an oauth account the user started linking from their profile', function () {
    /** @var User $user */
    $user = User::factory()->create();
    fakeSteamUser('76561190000000001');

    $this->actingAs($user)
        ->withSession(['oauth_link' => ['id' => 'steam', 'at' => now()->timestamp, 'nonce' => 'abc']])
        ->get('/auth/oauth/callback/steam?link=abc')
        ->assertRedirect(EditProfile::getUrl(['tab' => 'oauth::data::tab'], panel: 'app'))
        ->assertSessionMissing('oauth_link');

    expect($user->refresh()->oauth)->toHaveKey('steam', '76561190000000001');
});

it('does not link when the profile started linking too long ago', function () {
    /** @var User $user */
    $user = User::factory()->create();
    fakeSteamUser('76561190000000001');

    $this->actingAs($user)
        ->withSession(['oauth_link' => ['id' => 'steam', 'at' => now()->subMinutes(10)->timestamp, 'nonce' => 'abc']])
        ->get('/auth/oauth/callback/steam?link=abc')
        ->assertSessionMissing('oauth_link');

    expect($user->refresh()->oauth)->not->toHaveKey('steam');
});

it('does not link a steam account without the nonce from the profile', function () {
    /** @var User $user */
    $user = User::factory()->create();
    fakeSteamUser('76561190000000001');

    $this->actingAs($user)
        ->withSession(['oauth_link' => ['id' => 'steam', 'at' => now()->timestamp, 'nonce' => 'abc']])
        ->get('/auth/oauth/callback/steam?link=xyz')
        ->assertSessionMissing('oauth_link');

    expect($user->refresh()->oauth)->not->toHaveKey('steam');
});

it('starts linking from the profile page', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('redirectUrl')->once()->with(Mockery::on(fn (string $url) => $url === url('/auth/oauth/callback/steam?link=' . session('oauth_link.nonce'))));
    $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://steamcommunity.com/openid/login'));
    Socialite::shouldReceive('with')->with('steam')->andReturn($provider);

    Filament::setCurrentPanel('app');
    $this->actingAs($user);

    livewire(EditProfile::class)
        ->callAction(TestAction::make('oauth_steam')->schemaComponent(true))
        ->assertRedirect('https://steamcommunity.com/openid/login');

    expect(session('oauth_link.id'))->toBe('steam');
});
