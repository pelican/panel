<?php

use App\Events\ActivityLogged;
use App\Filament\Admin\Resources\Eggs\Pages\EditEgg;
use App\Filament\Admin\Resources\Mounts\Pages\CreateMount;
use App\Filament\Admin\Resources\Mounts\Pages\EditMount;
use App\Filament\Admin\Resources\Nodes\Pages\CreateNode;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\Egg;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);
});
afterEach(fn () => Filament::setCurrentPanel(null));

function makeMount(): Mount
{
    return Mount::query()->create([
        'uuid' => Str::uuid()->toString(),
        'name' => 'Audited Mount',
        'source' => '/mnt/audited',
        'target' => '/srv/audited',
        'read_only' => false,
        'user_mountable' => false,
    ]);
}

it('logs mount create with the admin as actor', function () {
    livewire(CreateMount::class)
        ->fillForm(['name' => 'Test Mount', 'source' => '/mnt/test', 'target' => '/srv/test'])
        ->call('create')
        ->assertHasNoFormErrors();

    $mount = Mount::query()->where('name', 'Test Mount')->firstOrFail();
    $this->assertActivityFor('mount:create', $this->admin, $mount);
});

it('logs mount update with an old-to-new diff', function () {
    $mount = makeMount();

    livewire(EditMount::class, ['record' => $mount->getKey()])
        ->fillForm(['name' => 'Renamed Mount'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertActivityFor('mount:update', $this->admin, $mount);
    Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $e) {
        if (!$e->is('mount:update')) {
            return false;
        }
        $changes = $e->model->properties['changes'];

        return $changes['name'] === ['old' => 'Audited Mount', 'new' => 'Renamed Mount'];
    });
});

it('logs nothing for a no-op save', function () {
    $mount = makeMount();

    livewire(EditMount::class, ['record' => $mount->getKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    Event::assertNotDispatched(ActivityLogged::class, fn (ActivityLogged $e) => $e->is('mount:update'));
});

it('logs mount delete', function () {
    $mount = makeMount();

    livewire(EditMount::class, ['record' => $mount->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertDatabaseMissing('mounts', ['id' => $mount->id]);
    $this->assertActivityFor('mount:delete', $this->admin, $mount);
});

it('logs node create', function () {
    livewire(CreateNode::class)
        ->fillForm(['name' => 'audited-node', 'fqdn' => '192.168.1.100'])
        ->call('create')
        ->assertHasNoFormErrors();

    $node = Node::query()->where('name', 'audited-node')->firstOrFail();
    $this->assertActivityFor('node:create', $this->admin, $node);
});

it('logs node update and masks daemon tokens in the diff', function () {
    $node = Node::factory()->create();

    livewire(EditNode::class, ['record' => $node->getKey()])
        ->fillForm(['name' => 'renamed-node'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertActivityFor('node:update', $this->admin, $node);
    Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $e) {
        if (!$e->is('node:update')) {
            return false;
        }

        return !str_contains(json_encode($e->model->properties), 'daemon_token_plain');
    });
});

it('logs node delete', function () {
    $node = Node::factory()->create();

    livewire(EditNode::class, ['record' => $node->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertActivityFor('node:delete', $this->admin, $node);
});

it('logs egg update', function () {
    $egg = Egg::factory()->create();

    livewire(EditEgg::class, ['record' => $egg->getKey()])
        ->fillForm(['name' => 'Renamed Egg'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertActivityFor('egg:update', $this->admin, $egg);
});

it('logs egg delete', function () {
    $egg = Egg::factory()->create();

    livewire(EditEgg::class, ['record' => $egg->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertActivityFor('egg:delete', $this->admin, $egg);
});

it('logs user create', function () {
    livewire(CreateUser::class)
        ->fillForm(['username' => 'auditeduser', 'email' => 'audited@example.com'])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'auditeduser')->firstOrFail();
    $this->assertActivityFor('user:create', $this->admin, $user);
});

it('logs user update and masks the password in the diff', function () {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['username' => 'renameduser', 'password' => 'NewPassword123!'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertActivityFor('user:update', $this->admin, $user);
    Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $e) {
        if (!$e->is('user:update')) {
            return false;
        }
        $changes = $e->model->properties['changes'];

        return $changes['password']['new'] === '********' && !str_contains(json_encode($e->model->properties), 'NewPassword123!');
    });
});

it('logs user delete', function () {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertActivityFor('user:delete', $this->admin, $user);
});

it('logs server update', function () {
    $server = Server::factory()->withNode()->create();

    livewire(EditServer::class, ['record' => $server->getKey()])
        ->fillForm(['name' => 'renamed-server', 'allocation_limit' => 0, 'database_limit' => 0])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertActivityFor('server:update', $this->admin, $server);
});

it('logs server delete', function () {
    $daemon = Mockery::mock(DaemonServerRepository::class);
    $daemon->shouldReceive('setServer')->andReturnSelf();
    $daemon->shouldReceive('getDetails')->andReturn(['state' => 'offline']);
    $daemon->shouldReceive('delete');
    $this->swap(DaemonServerRepository::class, $daemon);

    $server = Server::factory()->withNode()->create();

    livewire(EditServer::class, ['record' => $server->getKey()])
        ->callAction(TestAction::make('Delete'));

    $this->assertActivityFor('server:delete', $this->admin, $server);
});
