<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Eggs\Pages\EditEgg;
use App\Filament\Admin\Resources\Eggs\Pages\ListEggs;
use App\Filament\Admin\Resources\Eggs\Pages\ViewEgg;
use App\Models\Egg;
use App\Models\Role;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

afterEach(fn () => Filament::setCurrentPanel(null));

/** @param string[] $abilities */
function eggRole(string $name, array $abilities): Role
{
    $role = Role::factory()->create(['name' => $name, 'guard_name' => 'web']);

    foreach ($abilities as $ability) {
        $role->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }

    return $role;
}

it('lets a user with view permission open the view page', function () {
    $egg = Egg::factory()->create();

    [$user] = generateTestAccount([]);
    $user->syncRoles(eggRole('Egg Viewer', [
        RolePermissionModels::Egg->viewAny(),
        RolePermissionModels::Egg->view(),
    ]));

    $this->actingAs($user);
    livewire(ViewEgg::class, ['record' => $egg->getKey()])
        ->assertSuccessful();
});

it('forbids the view page without view permission', function () {
    $egg = Egg::factory()->create();

    [$user] = generateTestAccount([]);
    $user->syncRoles(eggRole('Node Viewer', [
        RolePermissionModels::Node->view(),
    ]));

    $this->actingAs($user);
    livewire(ViewEgg::class, ['record' => $egg->getKey()])
        ->assertForbidden();
});

it('forbids the edit page for a view-only user', function () {
    $egg = Egg::factory()->create();

    [$user] = generateTestAccount([]);
    $user->syncRoles(eggRole('Egg Viewer', [
        RolePermissionModels::Egg->viewAny(),
        RolePermissionModels::Egg->view(),
    ]));

    $this->actingAs($user);
    livewire(EditEgg::class, ['record' => $egg->getKey()])
        ->assertForbidden();
});

it('hides the icon action on the view page but keeps it on edit', function () {
    $egg = Egg::factory()->create();

    [$editor] = generateTestAccount([]);
    $editor->syncRoles(eggRole('Egg Editor', [
        RolePermissionModels::Egg->viewAny(),
        RolePermissionModels::Egg->view(),
        RolePermissionModels::Egg->update(),
    ]));

    [$viewer] = generateTestAccount([]);
    $viewer->syncRoles(eggRole('Egg Viewer', [
        RolePermissionModels::Egg->viewAny(),
        RolePermissionModels::Egg->view(),
    ]));

    $this->actingAs($editor);
    livewire(EditEgg::class, ['record' => $egg->getKey()])
        ->assertActionVisible(TestAction::make('upload_icon')->schemaComponent(true));

    $this->actingAs($viewer);
    livewire(ViewEgg::class, ['record' => $egg->getKey()])
        ->assertActionDoesNotExist(TestAction::make('upload_icon')->schemaComponent(true));
});

it('shows the view row action only when the user cannot edit', function () {
    $egg = Egg::factory()->create();

    [$viewer] = generateTestAccount([]);
    $viewer->syncRoles(eggRole('Egg Viewer', [
        RolePermissionModels::Egg->viewAny(),
        RolePermissionModels::Egg->view(),
    ]));

    [$editor] = generateTestAccount([]);
    $editor->syncRoles(eggRole('Egg Editor', [
        RolePermissionModels::Egg->viewAny(),
        RolePermissionModels::Egg->view(),
        RolePermissionModels::Egg->update(),
    ]));

    // table action urls resolve against the current panel, which defaults to 'app'
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs($viewer);
    livewire(ListEggs::class)
        ->assertActionVisible(TestAction::make('view')->table($egg));

    $this->actingAs($editor);
    livewire(ListEggs::class)
        ->assertActionHidden(TestAction::make('view')->table($egg));
});
