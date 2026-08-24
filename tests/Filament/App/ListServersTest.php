<?php

use App\Enums\CustomizationKey;
use App\Filament\App\Resources\Servers\Pages\ListServers;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('app');
});

it('persists a valid per-page selection to the user customization', function () {
    [$user] = generateTestAccount();

    $this->actingAs($user);

    livewire(ListServers::class)
        ->set('tableRecordsPerPage', 40)
        ->assertSuccessful();

    expect($user->refresh()->getCustomization(CustomizationKey::ServersPerPage))->toBe(40);
});

it('persists the per-page selection for a fresh user without any stored customization', function () {
    [$user] = generateTestAccount();
    $user->forceFill(['customization' => null])->saveQuietly();

    $this->actingAs($user);

    livewire(ListServers::class)
        ->set('tableRecordsPerPage', 20)
        ->assertSuccessful();

    expect($user->refresh()->getCustomization(CustomizationKey::ServersPerPage))->toBe(20);
});

it('does not persist a per-page value outside the offered options', function () {
    [$user] = generateTestAccount();

    $this->actingAs($user);

    livewire(ListServers::class)
        ->set('tableRecordsPerPage', 1000000)
        ->assertSuccessful();

    expect($user->refresh()->getCustomization(CustomizationKey::ServersPerPage))->toBe(0);
});

it('uses the stored per-page preference when no session value exists', function () {
    [$user] = generateTestAccount();
    $user->setCustomization(CustomizationKey::ServersPerPage, 30);

    $this->actingAs($user);

    livewire(ListServers::class)
        ->assertSet('tableRecordsPerPage', 30);
});

it('falls back to the layout default when the stored preference is not an offered option', function () {
    [$user] = generateTestAccount();
    $user->setCustomization(CustomizationKey::DashboardLayout, 'table');
    // 30 is a grid-only option; the list layout offers [10, 20, 50, 100].
    $user->setCustomization(CustomizationKey::ServersPerPage, 30);

    $this->actingAs($user);

    livewire(ListServers::class)
        ->assertSet('tableRecordsPerPage', 20);
});

it('keeps separate session per-page values for grid and list layouts', function () {
    [$user] = generateTestAccount();

    $this->actingAs($user);

    // Cache 40 in the grid layout's session key.
    livewire(ListServers::class)
        ->set('tableRecordsPerPage', 40);

    // Switching to the list layout must not read the incompatible grid value.
    $user->setCustomization(CustomizationKey::DashboardLayout, 'table');

    livewire(ListServers::class)
        ->assertSet('tableRecordsPerPage', 20);
});
