<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Roles\Pages\CreateRole;
use App\Filament\Admin\Resources\Roles\Pages\EditRole;
use App\Models\Role;
use Filament\Actions\DeleteAction;
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

it('can create a role with permissions', function () {
    livewire(CreateRole::class)
        ->fillForm([
            'name' => 'Support',
            'user_list' => [RolePermissionModels::User->viewAny()],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::findByName('Support');
    expect($role->hasPermissionTo(RolePermissionModels::User->viewAny()))->toBeTrue();
});

it('validates required fields when creating a role', function () {
    livewire(CreateRole::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('can edit a role', function () {
    $role = Role::factory()->create(['name' => 'Old Name', 'guard_name' => 'web']);

    livewire(EditRole::class, ['record' => $role->getKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'New Name']);
});

it('can delete a role with no users', function () {
    $role = Role::factory()->create(['name' => 'Doomed Role', 'guard_name' => 'web']);

    livewire(EditRole::class, ['record' => $role->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

it('non root admin without permission cannot create roles', function () {
    $role = Role::factory()->create(['name' => 'Egg Viewer', 'guard_name' => 'web']);
    // Egg permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Egg->viewAny(), 'web'));
    [$user] = generateTestAccount();
    $user->syncRoles($role);

    $this->actingAs($user);
    livewire(CreateRole::class)->assertForbidden();
});
