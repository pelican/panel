<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Nodes\Pages\CreateNode;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Models\Node;
use App\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

it('does not store empty wings config fields when editing a node', function () {
    $node = Node::factory()->create();

    livewire(EditNode::class, ['record' => $node->getKey()])
        ->fillForm(['name' => 'renamed-node'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($node->refresh()->daemon_config)->toBeNull();
});

it('can set wings config overrides on a node', function () {
    Http::fake();
    $node = Node::factory()->create();

    livewire(EditNode::class, ['record' => $node->getKey()])
        ->fillForm([
            'daemon_config.docker.container_pid_limit' => '1024',
            'daemon_config.docker.network.dns' => ['8.8.8.8'],
            'daemon_config.throttles.enabled' => '0',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($node->refresh()->daemon_config)->toBe([
        'docker' => [
            'network' => ['dns' => ['8.8.8.8']],
            'container_pid_limit' => 1024,
        ],
        'throttles' => ['enabled' => 0],
    ]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/update')
        && $request['docker']['container_pid_limit'] === 1024
        && $request['throttles']['enabled'] === false);
});

it('keeps custom ssl paths when the node is not using ssl', function () {
    $node = Node::factory()->create([
        'scheme' => 'http',
        'daemon_config' => ['api' => ['ssl' => ['cert' => '/etc/ssl/wings.crt', 'key' => '/etc/ssl/wings.key']]],
    ]);

    livewire(EditNode::class, ['record' => $node->getKey()])
        ->fillForm(['name' => 'renamed-node'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($node->refresh()->daemon_config)
        ->toBe(['api' => ['ssl' => ['cert' => '/etc/ssl/wings.crt', 'key' => '/etc/ssl/wings.key']]]);
});

it('keeps disabled wings config overrides stored as false', function () {
    $node = Node::factory()->create();
    // Simulate a value written as a JSON boolean, e.g. by an older API client.
    DB::table('nodes')->where('id', $node->id)->update(['daemon_config' => '{"throttles":{"enabled":false}}']);

    livewire(EditNode::class, ['record' => $node->getKey()])
        ->fillForm(['name' => 'renamed-node'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($node->refresh()->daemon_config)->toBe(['throttles' => ['enabled' => 0]]);
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
