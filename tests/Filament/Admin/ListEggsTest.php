<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Eggs\Pages\ListEggs;
use App\Models\Egg;
use App\Models\Role;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

// These tables live on the admin panel; without this the default 'app' panel is
// used and the resources' action urls fail to resolve.
beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('admin')));
afterEach(fn () => Filament::setCurrentPanel(null));

it('root admin can see all eggs', function () {
    $eggs = Egg::all();
    [$admin] = generateTestAccount([]);
    $admin = $admin->syncRoles(Role::getRootAdmin());

    $this->actingAs($admin);
    livewire(ListEggs::class)
        ->assertSuccessful()
        ->assertCountTableRecords($eggs->count())
        ->assertCanSeeTableRecords($eggs);
});

it('non root admin cannot see any eggs', function () {
    $role = Role::factory()->create(['name' => 'Node Viewer', 'guard_name' => 'web']);
    // Node Permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Node->viewAny(), 'web'));
    [$user] = generateTestAccount([]);

    $this->actingAs($user);
    livewire(ListEggs::class)
        ->assertForbidden();
});

it('non root admin with permissions can see eggs', function () {
    $role = Role::factory()->create(['name' => 'Egg Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Egg->viewAny(), 'web'));

    $eggs = Egg::all();
    [$user] = generateTestAccount([]);
    $user = $user->syncRoles($role);

    $this->actingAs($user);
    livewire(ListEggs::class)
        ->assertSuccessful()
        ->assertCountTableRecords($eggs->count())
        ->assertCanSeeTableRecords($eggs);
});
