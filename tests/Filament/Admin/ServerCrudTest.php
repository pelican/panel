<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Servers\Pages\CreateServer;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Filament\Actions\Testing\TestAction;
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

it('can create a server', function () {
    $daemon = Mockery::mock(DaemonServerRepository::class);
    $daemon->expects('setServer')->andReturnSelf();
    $daemon->expects('create')->andReturnUndefined();
    $this->swap(DaemonServerRepository::class, $daemon);

    $node = Node::factory()->create();
    $allocation = Allocation::factory()->create(['node_id' => $node->id, 'server_id' => null]);
    // A factory egg has no variables, so no required environment values get in the way.
    $egg = Egg::factory()->create();

    livewire(CreateServer::class)
        ->fillForm([
            'name' => 'test-server',
            'node_id' => $node->id,
            'owner_id' => $this->admin->id,
            'allocation_id' => $allocation->id,
            'egg_id' => $egg->id,
            'startup' => 'java -jar test.jar',
            'image' => 'ghcr.io/pelican-eggs/yolks:java_21',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('servers', [
        'name' => 'test-server',
        'node_id' => $node->id,
        'owner_id' => $this->admin->id,
        'egg_id' => $egg->id,
    ]);
});

it('can edit a server', function () {
    $server = Server::factory()->withNode()->create();

    // Renaming is excluded from the daemon sync diff, so no daemon call happens.
    livewire(EditServer::class, ['record' => $server->getKey()])
        ->fillForm([
            'name' => 'renamed-server',
            'allocation_limit' => 0,
            'database_limit' => 0,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('servers', ['id' => $server->id, 'name' => 'renamed-server']);
});

it('can delete a server', function () {
    $daemon = Mockery::mock(DaemonServerRepository::class);
    $daemon->shouldReceive('setServer')->andReturnSelf();
    // Rendering the edit page polls the server's container status.
    $daemon->shouldReceive('getDetails')->andReturn(['state' => 'offline']);
    $daemon->shouldReceive('delete');
    $this->swap(DaemonServerRepository::class, $daemon);

    $server = Server::factory()->withNode()->create();

    livewire(EditServer::class, ['record' => $server->getKey()])
        ->callAction(TestAction::make('Delete'));

    $this->assertDatabaseMissing('servers', ['id' => $server->id]);
});

it('non root admin without permission cannot create servers', function () {
    $role = Role::factory()->create(['name' => 'Egg Viewer', 'guard_name' => 'web']);
    // Egg permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Egg->viewAny(), 'web'));
    [$user] = generateTestAccount();
    $user->syncRoles($role);

    $this->actingAs($user);
    livewire(CreateServer::class)->assertForbidden();
});
