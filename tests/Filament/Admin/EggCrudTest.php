<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Eggs\Pages\CreateEgg;
use App\Filament\Admin\Resources\Eggs\Pages\EditEgg;
use App\Models\Egg;
use App\Models\Role;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);
});
afterEach(fn () => Filament::setCurrentPanel(null));

it('can create an egg', function () {
    livewire(CreateEgg::class)
        ->fillForm([
            'name' => 'Test Egg',
            'author' => 'test@example.com',
            'startup_commands' => ['Default' => 'java -jar server.jar'],
            'docker_images' => ['Java 21' => 'ghcr.io/pelican-eggs/yolks:java_21'],
            'config_stop' => 'stop',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('eggs', [
        'name' => 'Test Egg',
        'author' => 'test@example.com',
    ]);
});

it('validates required fields when creating an egg', function () {
    livewire(CreateEgg::class)
        ->fillForm([
            'author' => 'test@example.com',
            'config_stop' => 'stop',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('can edit an egg', function () {
    $egg = Egg::query()->firstOrFail();

    livewire(EditEgg::class, ['record' => $egg->getKey()])
        ->fillForm(['name' => 'Renamed Egg'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('eggs', ['id' => $egg->id, 'name' => 'Renamed Egg']);
});

it('non root admin without permission cannot create eggs', function () {
    $role = Role::factory()->create(['name' => 'Node Viewer', 'guard_name' => 'web']);
    // Node permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Node->viewAny(), 'web'));
    [$user] = generateTestAccount();
    $user->syncRoles($role);

    $this->actingAs($user);
    livewire(CreateEgg::class)->assertForbidden();
});
