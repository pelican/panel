<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Admin\Resources\Servers\Pages\ListServers;
use App\Filament\Admin\Resources\Servers\Pages\ViewServer;
use App\Filament\Admin\Resources\Servers\RelationManagers\AllocationsRelationManager;
use App\Models\Allocation;
use App\Models\Role;
use App\Models\Server;
use App\Models\ServerVariable;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

afterEach(fn () => Filament::setCurrentPanel(null));

/** @param string[] $abilities */
function serverRole(string $name, array $abilities): Role
{
    $role = Role::factory()->create(['name' => $name, 'guard_name' => 'web']);

    foreach ($abilities as $ability) {
        $role->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }

    return $role;
}

function serverAllocation(Server $server): Allocation
{
    return Allocation::factory()->create([
        'node_id' => $server->node->getKey(),
        'server_id' => $server->getKey(),
    ]);
}

it('lets a user with view permission open the view page', function () {
    [$user, $server] = generateTestAccount([]);
    $user->syncRoles(serverRole('Server Viewer', [
        RolePermissionModels::Server->viewAny(),
        RolePermissionModels::Server->view(),
    ]));

    $this->actingAs($user);
    livewire(ViewServer::class, ['record' => $server->getKey()])
        ->assertSuccessful();
});

it('forbids the view page without view permission', function () {
    [$user, $server] = generateTestAccount([]);
    $user->syncRoles(serverRole('Egg Viewer', [
        RolePermissionModels::Egg->view(),
    ]));

    $this->actingAs($user);
    livewire(ViewServer::class, ['record' => $server->getKey()])
        ->assertForbidden();
});

it('forbids the edit page for a view-only user', function () {
    [$user, $server] = generateTestAccount([]);
    $user->syncRoles(serverRole('Server Viewer', [
        RolePermissionModels::Server->viewAny(),
        RolePermissionModels::Server->view(),
    ]));

    $this->actingAs($user);
    livewire(EditServer::class, ['record' => $server->getKey()])
        ->assertForbidden();
});

it('does not materialize server variables when the view page is mounted', function () {
    [$user, $server] = generateTestAccount([]);
    $user->syncRoles(serverRole('Server Viewer', [
        RolePermissionModels::Server->viewAny(),
        RolePermissionModels::Server->view(),
    ]));

    ServerVariable::query()->where('server_id', $server->getKey())->delete();
    $before = ServerVariable::query()->where('server_id', $server->getKey())->count();

    $this->actingAs($user);
    livewire(ViewServer::class, ['record' => $server->getKey()])
        ->assertSuccessful();

    expect(ServerVariable::query()->where('server_id', $server->getKey())->count())->toBe($before);
});

it('hides the reinstall action on the view page but keeps it on edit', function () {
    [$editor, $server] = generateTestAccount([]);
    $editor->syncRoles(serverRole('Server Editor', [
        RolePermissionModels::Server->viewAny(),
        RolePermissionModels::Server->view(),
        RolePermissionModels::Server->update(),
    ]));

    [$viewer] = generateTestAccount([]);
    $viewer->syncRoles(serverRole('Server Viewer', [
        RolePermissionModels::Server->viewAny(),
        RolePermissionModels::Server->view(),
    ]));

    $this->actingAs($editor);
    livewire(EditServer::class, ['record' => $server->getKey()])
        ->assertActionVisible(TestAction::make('exclude_reinstall')->schemaComponent(true));

    $this->actingAs($viewer);
    livewire(ViewServer::class, ['record' => $server->getKey()])
        ->assertActionDoesNotExist(TestAction::make('exclude_reinstall')->schemaComponent(true));
});

it('shows the view row action only when the user cannot edit', function () {
    [$viewer, $server] = generateTestAccount([]);
    $viewer->syncRoles(serverRole('Server Viewer', [
        RolePermissionModels::Server->viewAny(),
        RolePermissionModels::Server->view(),
    ]));

    [$editor] = generateTestAccount([]);
    $editor->syncRoles(serverRole('Server Editor', [
        RolePermissionModels::Server->viewAny(),
        RolePermissionModels::Server->view(),
        RolePermissionModels::Server->update(),
    ]));

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs($viewer);
    livewire(ListServers::class)
        ->assertActionVisible(TestAction::make('view')->table($server));

    $this->actingAs($editor);
    livewire(ListServers::class)
        ->assertActionHidden(TestAction::make('view')->table($server));
});

it('keeps the server relation managers read-only on the view page', function () {
    [$editor, $server] = generateTestAccount([]);
    $editor->syncRoles(serverRole('Server Editor', [
        RolePermissionModels::Server->viewAny(),
        RolePermissionModels::Server->view(),
        RolePermissionModels::Server->update(),
    ]));
    $allocation = serverAllocation($server);

    // editor can update, so the view operation is what gates this write
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($editor);

    $alias = $allocation->ip_alias;

    livewire(AllocationsRelationManager::class, [
        'ownerRecord' => $server,
        'pageClass' => ViewServer::class,
    ])
        ->assertActionHidden(TestAction::make(CreateAction::class)->table())
        ->call('updateTableColumnState', 'ip_alias', (string) $allocation->getKey(), 'hacked-alias');

    expect($allocation->refresh()->ip_alias)->toBe($alias);
});
