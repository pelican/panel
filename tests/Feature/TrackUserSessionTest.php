<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Helpers\UserAgent;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Users\UserSessionService;
use App\Services\Users\UserUpdateService;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    /** @var User $user */
    $user = User::factory()->create();
    $this->user = $user;
    $this->url = EditProfile::getUrl(panel: 'app');
});

it('registers the device on the first request and reuses it afterwards', function () {
    $this->actingAs($this->user)->get($this->url)->assertOk();
    $this->get($this->url)->assertOk();

    $sessions = $this->user->sessions()->get();

    expect($sessions)->toHaveCount(1)
        ->and($sessions->first()->last_active_at)->not->toBeNull()
        ->and(session(UserSessionService::SESSION_KEY))->toBe($sessions->first()->id);
});

it('signs out a session whose device was revoked', function () {
    $this->actingAs($this->user)->get($this->url)->assertOk();

    $this->user->sessions()->delete();

    $this->withHeader('Accept', 'text/html')->get($this->url)->assertRedirect(Filament::getLoginUrl());
    $this->assertGuest();
});

it('signs out a device after the inactivity limit', function () {
    $this->actingAs($this->user)->get($this->url)->assertOk();

    $session = $this->user->sessions()->first();
    $session->forceFill(['last_active_at' => now()->subDays(15)])->save();

    $this->withHeader('Accept', 'text/html')->get($this->url)->assertRedirect(Filament::getLoginUrl());
    $this->assertGuest();

    expect(UserSession::query()->find($session->id))->toBeNull();
});

it('keeps the device signed in when inactivity sign-out is disabled', function () {
    config()->set('panel.auth.session_inactivity_days', 0);

    $this->actingAs($this->user)->get($this->url)->assertOk();
    $this->user->sessions()->first()->forceFill(['last_active_at' => now()->subDays(60)])->save();

    $this->get($this->url)->assertOk();
});

it('prunes inactive and expired devices', function () {
    $active = UserSession::factory()->create(['user_id' => $this->user->id, 'last_active_at' => now()->subDays(3)]);
    $inactive = UserSession::factory()->create(['user_id' => $this->user->id, 'last_active_at' => now()->subDays(15)]);
    $expired = UserSession::factory()->create(['user_id' => $this->user->id, 'remember' => false, 'last_active_at' => now()->subDay()]);

    $this->artisan('model:prune', ['--model' => [UserSession::class]])->assertSuccessful();

    expect(UserSession::query()->pluck('id')->all())->toBe([$active->id])
        ->and(UserSession::query()->find($inactive->id))->toBeNull()
        ->and(UserSession::query()->find($expired->id))->toBeNull();
});

it('keeps the remember token of other devices when signing out an inactive device', function () {
    $this->user->forceFill(['remember_token' => 'remember-token'])->save();

    $this->actingAs($this->user)->get($this->url)->assertOk();
    $this->user->sessions()->first()->forceFill(['last_active_at' => now()->subDays(15)])->save();

    $this->withHeader('Accept', 'text/html')->get($this->url)->assertRedirect(Filament::getLoginUrl());

    expect($this->user->fresh()->remember_token)->toBe('remember-token');
});

it('signs out a revoked device on session-authenticated API requests', function () {
    $this->actingAs($this->user)->get($this->url)->assertOk();

    $this->user->sessions()->delete();

    $this->withCredentials()
        ->withCookie(config('session.cookie'), 'session')
        ->getJson(route('api:client.account'))
        ->assertUnauthorized();
    $this->assertGuest('web');
});

it('does not prune or hide a session whose last activity is within the touch interval of expiring', function () {
    $session = UserSession::factory()->create([
        'user_id' => $this->user->id,
        'remember' => false,
        'last_active_at' => now()->subMinutes((int) config('session.lifetime', 120))->subSeconds(UserSessionService::TOUCH_INTERVAL_SECONDS / 2),
    ]);

    $this->artisan('model:prune', ['--model' => [UserSession::class]])->assertSuccessful();

    expect(UserSession::query()->find($session->id))->not->toBeNull()
        ->and(UserSession::query()->active()->find($session->id))->not->toBeNull();
});

it('signs out every device when an admin changes the user\'s password', function () {
    UserSession::factory()->count(2)->create(['user_id' => $this->user->id]);

    app(UserUpdateService::class)->handle($this->user, ['password' => 'New-Password-123!']);

    expect($this->user->sessions()->count())->toBe(0);
});

it('labels devices from the user agent', function (string $userAgent, ?string $label, string $type) {
    $agent = UserAgent::parse($userAgent);

    expect($agent->label())->toBe($label)
        ->and($agent->type)->toBe($type);
})->with([
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'Chrome on Windows', UserAgent::TYPE_DESKTOP],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0', 'Edge on Windows', UserAgent::TYPE_DESKTOP],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15', 'Safari on macOS', UserAgent::TYPE_DESKTOP],
    ['Mozilla/5.0 (X11; Linux x86_64; rv:132.0) Gecko/20100101 Firefox/132.0', 'Firefox on Linux', UserAgent::TYPE_DESKTOP],
    ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', 'Chrome on Android', UserAgent::TYPE_MOBILE],
    ['Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/131.0 Mobile/15E148 Safari/604.1', 'Chrome on iPadOS', UserAgent::TYPE_TABLET],
    ['curl/8.4.0', null, UserAgent::TYPE_UNKNOWN],
]);
