<?php

use App\Facades\Activity;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\UserSSHKey;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('app');
});

it('renders the activity tab when the user has activity logs', function () {
    /** @var User $user */
    $user = User::factory()->create();

    // Prior to the fix the activity repeater's TextEntry closure declared a
    // `$log` parameter that Filament could not inject, throwing a
    // BindingResolutionException while rendering this page. A logged activity
    // is required so the repeater actually renders a row and evaluates the closure.
    Activity::event('user:api-key.create')
        ->actor($user)
        ->subject($user)
        ->property('identifier', 'pacc_test')
        ->log();

    $this->actingAs($user);

    livewire(EditProfile::class)
        ->assertSuccessful();
});

it('cannot delete another user\'s api key by tampering with the repeater state', function () {
    /** @var User $user */
    $user = User::factory()->create();
    /** @var User $victim */
    $victim = User::factory()->create();
    $ownKey = ApiKey::factory()->create(['user_id' => $user->id, 'key_type' => ApiKey::TYPE_ACCOUNT]);
    $victimKey = ApiKey::factory()->create(['user_id' => $victim->id, 'key_type' => ApiKey::TYPE_ACCOUNT]);

    $this->actingAs($user);

    $component = livewire(EditProfile::class);
    $item = array_key_first($component->get('data.api_keys'));

    $component
        ->set("data.api_keys.$item.id", $victimKey->id)
        ->callFormComponentAction('api_keys', 'delete', arguments: ['item' => $item])
        ->assertSuccessful();

    expect(ApiKey::query()->find($victimKey->id))->not->toBeNull()
        ->and(ApiKey::query()->find($ownKey->id))->not->toBeNull();
});

it('cannot delete another user\'s ssh key by tampering with the repeater state', function () {
    /** @var User $user */
    $user = User::factory()->create();
    /** @var User $victim */
    $victim = User::factory()->create();
    $ownKey = UserSSHKey::factory()->create(['user_id' => $user->id]);
    $victimKey = UserSSHKey::factory()->create(['user_id' => $victim->id]);

    $this->actingAs($user);

    $component = livewire(EditProfile::class);
    $item = array_key_first($component->get('data.ssh_keys'));

    $component
        ->set("data.ssh_keys.$item.id", $victimKey->id)
        ->callFormComponentAction('ssh_keys', 'delete', arguments: ['item' => $item])
        ->assertSuccessful();

    expect(UserSSHKey::query()->find($victimKey->id))->not->toBeNull()
        ->and(UserSSHKey::query()->find($ownKey->id))->not->toBeNull();
});

it('deletes the user\'s own api key', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $key = ApiKey::factory()->create(['user_id' => $user->id, 'key_type' => ApiKey::TYPE_ACCOUNT]);

    $this->actingAs($user);

    $component = livewire(EditProfile::class);
    $item = array_key_first($component->get('data.api_keys'));

    $component
        ->callFormComponentAction('api_keys', 'delete', arguments: ['item' => $item])
        ->assertSuccessful();

    expect(ApiKey::query()->find($key->id))->toBeNull();
});
