<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Nodes\Pages\ListNodes;
use App\Filament\Admin\Resources\Nodes\Pages\ViewNode;
use App\Filament\Admin\Resources\Nodes\RelationManagers\AllocationsRelationManager;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

afterEach(fn () => Filament::setCurrentPanel(null));

/** @param string[] $abilities */
function nodeRole(string $name, array $abilities): Role
{
    $role = Role::factory()->create(['name' => $name, 'guard_name' => 'web']);

    foreach ($abilities as $ability) {
        $role->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }

    return $role;
}

it('lets a user with view permission open the view page', function () {
    $node = Node::factory()->create();

    [$user] = generateTestAccount([]);
    $user->syncRoles(nodeRole('Node Viewer', [
        RolePermissionModels::Node->viewAny(),
        RolePermissionModels::Node->view(),
    ]));

    $this->actingAs($user);
    livewire(ViewNode::class, ['record' => $node->getKey()])
        ->assertSuccessful();
});

it('forbids the view page without view permission', function () {
    $node = Node::factory()->create();

    [$user] = generateTestAccount([]);
    $user->syncRoles(nodeRole('Egg Viewer', [
        RolePermissionModels::Egg->view(),
    ]));

    $this->actingAs($user);
    livewire(ViewNode::class, ['record' => $node->getKey()])
        ->assertForbidden();
});

it('forbids the edit page for a view-only user', function () {
    $node = Node::factory()->create();

    [$user] = generateTestAccount([]);
    $user->syncRoles(nodeRole('Node Viewer', [
        RolePermissionModels::Node->viewAny(),
        RolePermissionModels::Node->view(),
    ]));

    $this->actingAs($user);
    livewire(EditNode::class, ['record' => $node->getKey()])
        ->assertForbidden();
});

it('hides the reset-token action on the view page but keeps it on edit', function () {
    $node = Node::factory()->create();

    [$editor] = generateTestAccount([]);
    $editor->syncRoles(nodeRole('Node Editor', [
        RolePermissionModels::Node->viewAny(),
        RolePermissionModels::Node->view(),
        RolePermissionModels::Node->update(),
    ]));

    [$viewer] = generateTestAccount([]);
    $viewer->syncRoles(nodeRole('Node Viewer', [
        RolePermissionModels::Node->viewAny(),
        RolePermissionModels::Node->view(),
    ]));

    $this->actingAs($editor);
    livewire(EditNode::class, ['record' => $node->getKey()])
        ->assertActionVisible(TestAction::make('exclude_resetKey')->schemaComponent(true));

    $this->actingAs($viewer);
    livewire(ViewNode::class, ['record' => $node->getKey()])
        ->assertActionDoesNotExist(TestAction::make('exclude_resetKey')->schemaComponent(true));
});

it('does not expose the wings daemon token on the view page', function () {
    $node = Node::factory()->create();

    [$viewer] = generateTestAccount([]);
    $viewer->syncRoles(nodeRole('Node Viewer', [
        RolePermissionModels::Node->viewAny(),
        RolePermissionModels::Node->view(),
    ]));

    $this->actingAs($viewer);
    livewire(ViewNode::class, ['record' => $node->getKey()])
        ->assertDontSee($node->daemon_token)
        ->assertDontSee($node->daemon_token_id);
});

it('shows the view row action only when the user cannot edit', function () {
    $node = Node::factory()->create();

    [$viewer] = generateTestAccount([]);
    $viewer->syncRoles(nodeRole('Node Viewer', [
        RolePermissionModels::Node->viewAny(),
        RolePermissionModels::Node->view(),
    ]));

    [$editor] = generateTestAccount([]);
    $editor->syncRoles(nodeRole('Node Editor', [
        RolePermissionModels::Node->viewAny(),
        RolePermissionModels::Node->view(),
        RolePermissionModels::Node->update(),
    ]));

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs($viewer);
    livewire(ListNodes::class)
        ->assertActionVisible(TestAction::make('view')->table($node));

    $this->actingAs($editor);
    livewire(ListNodes::class)
        ->assertActionHidden(TestAction::make('view')->table($node));
});

it('keeps the node relation managers read-only on the view page', function () {
    $node = Node::factory()->create();
    $server = Server::factory()->withNode($node)->create();
    $allocation = Allocation::factory()->create([
        'node_id' => $node->getKey(),
        'server_id' => $server->getKey(),
    ]);

    // user has update permission, so the block can only come from the view operation
    [$editor] = generateTestAccount([]);
    $editor->syncRoles(nodeRole('Node Editor', [
        RolePermissionModels::Node->viewAny(),
        RolePermissionModels::Node->view(),
        RolePermissionModels::Node->update(),
    ]));

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($editor);

    $alias = $allocation->ip_alias;

    livewire(AllocationsRelationManager::class, [
        'ownerRecord' => $node,
        'pageClass' => ViewNode::class,
    ])
        ->assertActionHidden(TestAction::make('create')->table())
        ->call('updateTableColumnState', 'ip_alias', (string) $allocation->getKey(), 'hacked-alias');

    expect($allocation->refresh()->ip_alias)->toBe($alias);
});
