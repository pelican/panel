<?php

use App\Enums\SubuserPermission;
use App\Filament\Server\Pages\Startup;
use App\Models\ServerVariable;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(fn () => Filament::setCurrentPanel('server'));
afterEach(fn () => Filament::setCurrentPanel(null));

it('forbids updating a startup variable that belongs to another server', function () {
    [$user, $server] = generateTestAccount();
    $foreign = createServerModel();
    $foreign->ensureVariablesExist();

    /** @var ServerVariable $foreignVariable */
    $foreignVariable = $foreign->serverVariables()->firstOrFail();
    $foreignVariable->update(['variable_value' => 'secret-value']);

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(Startup::class)
        ->call('update', 'changed', $foreignVariable->id)
        ->assertForbidden();

    expect($foreignVariable->refresh()->variable_value)->toBe('secret-value');
});

it('forbids updating a startup variable the user cannot view', function () {
    [$user, $server] = generateTestAccount();
    $server->ensureVariablesExist();

    /** @var ServerVariable $variable */
    $variable = $server->serverVariables()->firstOrFail();
    $variable->variable->update(['user_viewable' => false, 'user_editable' => true]);
    $variable->update(['variable_value' => 'hidden']);

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(Startup::class)
        ->call('update', 'changed', $variable->id)
        ->assertForbidden();

    expect($variable->refresh()->variable_value)->toBe('hidden');
});

it('updates a startup variable on the tenant server', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::StartupRead, SubuserPermission::StartupUpdate]);
    $server->ensureVariablesExist();

    /** @var ServerVariable $variable */
    $variable = $server->serverVariables()->firstOrFail();
    $variable->variable->update(['user_viewable' => true, 'user_editable' => true, 'rules' => ['string']]);

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(Startup::class)
        ->call('update', 'changed', $variable->id)
        ->assertSuccessful();

    expect($variable->refresh()->variable_value)->toBe('changed');
});
