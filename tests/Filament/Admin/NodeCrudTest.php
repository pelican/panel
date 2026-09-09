<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Nodes\Pages\CreateNode;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Models\Node;
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

it('can create a node', function () {
    // An IP avoids the DNS resolution check that fqdn hostnames go through.
    livewire(CreateNode::class)
        ->fillForm([
            'name' => 'test-node',
            'fqdn' => '192.168.1.100',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('nodes', [
        'name' => 'test-node',
        'fqdn' => '192.168.1.100',
    ]);
});

it('validates required fields when creating a node', function () {
    livewire(CreateNode::class)
        ->fillForm([
            'fqdn' => '192.168.1.100',
            'name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('can edit a node', function () {
    $node = Node::factory()->create();

    // Renaming is in EditNode's excluded-fields list, so no daemon call happens.
    livewire(EditNode::class, ['record' => $node->getKey()])
        ->fillForm(['name' => 'renamed-node'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('nodes', ['id' => $node->id, 'name' => 'renamed-node']);
});

it('non root admin without permission cannot create nodes', function () {
    $role = Role::factory()->create(['name' => 'Egg Viewer', 'guard_name' => 'web']);
    // Egg permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Egg->viewAny(), 'web'));
    [$user] = generateTestAccount();
    $user->syncRoles($role);

    $this->actingAs($user);
    livewire(CreateNode::class)->assertForbidden();
});
