<?php

use App\Enums\CustomizationKey;
use App\Enums\SubuserPermission;
use App\Filament\App\Resources\Servers\Pages\ListServers;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;

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

function powerRequest(string $uuid, string $action): Closure
{
    return fn ($request) => str_ends_with($request->url(), "/api/servers/$uuid/power") && $request['action'] === $action;
}

it('forbids power actions on a server the user has no access to', function () {
    Http::fake(['*' => Http::response(['state' => 'running', 'utilization' => []])]);

    [$user] = generateTestAccount();
    $server = createServerModel();

    $this->actingAs($user);

    livewire(ListServers::class)
        ->call('powerAction', $server->id, 'kill')
        ->assertForbidden();

    Http::assertNotSent(powerRequest($server->uuid, 'kill'));
});

it('forbids power actions the subuser lacks permission for', function () {
    Http::fake(['*' => Http::response(['state' => 'running', 'utilization' => []])]);

    [$user, $server] = generateTestAccount([SubuserPermission::ControlStart]);

    $this->actingAs($user);

    livewire(ListServers::class)
        ->call('powerAction', $server->id, 'stop')
        ->assertForbidden();

    Http::assertNotSent(powerRequest($server->uuid, 'stop'));
});

it('rejects unknown power actions', function () {
    Http::fake(['*' => Http::response(['state' => 'running', 'utilization' => []])]);

    [$user, $server] = generateTestAccount();

    $this->actingAs($user);

    livewire(ListServers::class)
        ->call('powerAction', $server->id, 'explode')
        ->assertStatus(422);

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), "/api/servers/{$server->uuid}/power"));
});

it('sends power actions for a server the user owns', function () {
    Http::fake(['*' => Http::response(['state' => 'running', 'utilization' => []])]);

    [$user, $server] = generateTestAccount();

    $this->actingAs($user);

    livewire(ListServers::class)
        ->call('powerAction', $server->id, 'start')
        ->assertSuccessful();

    Http::assertSent(powerRequest($server->uuid, 'start'));
});
