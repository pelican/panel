<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\DatabaseHosts\Pages\CreateDatabaseHost;
use App\Filament\Admin\Resources\DatabaseHosts\Pages\EditDatabaseHost;
use App\Models\DatabaseHost;
use App\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);
});
afterEach(fn () => Filament::setCurrentPanel(null));

// The host services confirm access via DatabaseHost->buildConnection()->getPdo(),
// so fake the remote connection while leaving the panel's own connection real.
function fakeRemoteDatabaseConnection(?Exception $exception = null): void
{
    $connection = Mockery::mock(Connection::class);

    if ($exception) {
        $connection->shouldReceive('getPdo')->andThrow($exception);
    } else {
        $connection->shouldReceive('getPdo')->andReturn(Mockery::mock(PDO::class));
    }

    // A proxied partial keeps the real manager's state; DB::partialMock() would
    // build an unconstructed manager whose $app is null and break page rendering.
    $manager = Mockery::mock(app('db'));
    $manager->shouldReceive('build')->andReturn($connection);
    DB::swap($manager);
    app()->instance('db', $manager);
}

it('can create a database host', function () {
    fakeRemoteDatabaseConnection();

    livewire(CreateDatabaseHost::class)
        ->fillForm([
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'local-mysql',
            'username' => 'pelicanuser',
            'password' => 'secret1234',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('database_hosts', [
        'name' => 'local-mysql',
        'host' => '127.0.0.1',
        'username' => 'pelicanuser',
    ]);
});

it('does not save a database host when the connection fails', function () {
    fakeRemoteDatabaseConnection(new PDOException('Connection refused'));

    livewire(CreateDatabaseHost::class)
        ->fillForm([
            'host' => '10.0.0.99',
            'port' => 3306,
            'name' => 'unreachable',
            'username' => 'pelicanuser',
            'password' => 'secret1234',
        ])
        ->call('create');

    $this->assertDatabaseMissing('database_hosts', ['name' => 'unreachable']);
});

it('can edit a database host', function () {
    fakeRemoteDatabaseConnection();

    $databaseHost = DatabaseHost::factory()->create();

    livewire(EditDatabaseHost::class, ['record' => $databaseHost->getKey()])
        ->fillForm(['name' => 'renamed-host'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('database_hosts', ['id' => $databaseHost->id, 'name' => 'renamed-host']);
});

it('non root admin without permission cannot create database hosts', function () {
    $role = Role::factory()->create(['name' => 'Egg Viewer', 'guard_name' => 'web']);
    // Egg permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Egg->viewAny(), 'web'));
    [$user] = generateTestAccount();
    $user->syncRoles($role);

    $this->actingAs($user);
    livewire(CreateDatabaseHost::class)->assertForbidden();
});
