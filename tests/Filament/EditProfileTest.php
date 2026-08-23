<?php

use App\Facades\Activity;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
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
