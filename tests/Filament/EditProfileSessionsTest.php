<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Users\UserSessionService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('app');

    /** @var User $user */
    $user = User::factory()->create();
    $this->user = $user;

    $this->current = UserSession::factory()->create(['user_id' => $user->id, 'last_active_at' => now()]);
    $this->other = UserSession::factory()->create([
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
        'last_active_at' => now()->subDays(2),
    ]);

    $this->actingAs($user)->withSession([UserSessionService::SESSION_KEY => $this->current->id]);
});

function sessionItemKey(mixed $component, UserSession $session): string
{
    return collect($component->get('data.sessions'))->search(fn (array $item) => $item['id'] === $session->id);
}

it('lists signed-in devices with the current one first', function () {
    $component = livewire(EditProfile::class)->assertSuccessful();

    $items = array_values($component->get('data.sessions'));

    expect($items)->toHaveCount(2)
        ->and($items[0]['id'])->toBe($this->current->id)
        ->and($items[0]['is_current'])->toBeTrue()
        ->and($items[1]['display'])->toBe('Safari on iOS')
        ->and($items[1]['is_current'])->toBeFalse();
});

it('hides devices that are past the inactivity limit or whose session expired', function () {
    UserSession::factory()->create(['user_id' => $this->user->id, 'last_active_at' => now()->subDays(15)]);
    UserSession::factory()->create(['user_id' => $this->user->id, 'remember' => false, 'last_active_at' => now()->subDay()]);

    $component = livewire(EditProfile::class);

    expect($component->get('data.sessions'))->toHaveCount(2);
});

it('signs out another device', function () {
    $component = livewire(EditProfile::class);

    $component
        ->callFormComponentAction('sessions', 'delete', arguments: ['item' => sessionItemKey($component, $this->other)])
        ->assertSuccessful();

    expect(UserSession::query()->find($this->other->id))->toBeNull()
        ->and(UserSession::query()->find($this->current->id))->not->toBeNull()
        ->and($component->get('data.sessions'))->toHaveCount(1);
});

it('does not sign out the current device from the list', function () {
    $component = livewire(EditProfile::class);
    $item = sessionItemKey($component, $this->current);

    // The action is hidden for the current device; tamper the flag to reach it anyway.
    $component
        ->set("data.sessions.$item.is_current", false)
        ->callFormComponentAction('sessions', 'delete', arguments: ['item' => $item]);

    expect(UserSession::query()->find($this->current->id))->not->toBeNull();
});

it('cannot sign out another user\'s device by tampering with the repeater state', function () {
    $victim = UserSession::factory()->create(['user_id' => User::factory()->create()->id]);

    $component = livewire(EditProfile::class);
    $item = sessionItemKey($component, $this->other);

    $component
        ->set("data.sessions.$item.id", $victim->id)
        ->callFormComponentAction('sessions', 'delete', arguments: ['item' => $item])
        ->assertSuccessful();

    expect(UserSession::query()->find($victim->id))->not->toBeNull();
});

it('renames a device and resets the name when cleared', function () {
    $component = livewire(EditProfile::class);
    $item = sessionItemKey($component, $this->other);

    $component
        ->callFormComponentAction('sessions', 'rename', data: ['name' => '  Work phone  '], arguments: ['item' => $item])
        ->assertHasNoFormComponentActionErrors();

    expect($this->other->refresh()->name)->toBe('Work phone')
        ->and($component->get("data.sessions.$item.display"))->toBe('Work phone');

    $component->callFormComponentAction('sessions', 'rename', data: ['name' => ''], arguments: ['item' => $item]);

    expect($this->other->refresh()->name)->toBeNull()
        ->and($component->get("data.sessions.$item.display"))->toBe('Safari on iOS');
});

it('cannot rename another user\'s device by tampering with the repeater state', function () {
    $victim = UserSession::factory()->create(['user_id' => User::factory()->create()->id]);

    $component = livewire(EditProfile::class);
    $item = sessionItemKey($component, $this->other);

    $component
        ->set("data.sessions.$item.id", $victim->id)
        ->callFormComponentAction('sessions', 'rename', data: ['name' => 'Hacked'], arguments: ['item' => $item]);

    expect($victim->refresh()->name)->toBeNull();
});

it('signs out of all other devices', function () {
    UserSession::factory()->count(2)->create(['user_id' => $this->user->id]);
    $unrelated = UserSession::factory()->create(['user_id' => User::factory()->create()->id]);

    livewire(EditProfile::class)
        ->callAction(TestAction::make('exclude_sign_out_other_sessions')->schemaComponent(true))
        ->assertSuccessful()
        ->assertSet('data.sessions', fn (array $sessions) => count($sessions) === 1);

    expect($this->user->sessions()->pluck('id')->all())->toBe([$this->current->id])
        ->and(UserSession::query()->find($unrelated->id))->not->toBeNull();
});

it('signs out other devices when the password is changed', function () {
    livewire(EditProfile::class)
        ->fillForm(['password' => 'New-Password-123!', 'passwordConfirmation' => 'New-Password-123!'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->user->sessions()->pluck('id')->all())->toBe([$this->current->id]);
});
