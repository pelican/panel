<?php

use App\Enums\RolePermissionModels;
use App\Events\ActivityLogged;
use App\Filament\Admin\Resources\ApiKeys\Pages\CreateApiKey;
use App\Filament\Admin\Resources\ApiKeys\Pages\ListApiKeys;
use App\Filament\Admin\Resources\BackupHosts\Pages\CreateBackupHost;
use App\Filament\Admin\Resources\BackupHosts\Pages\EditBackupHost;
use App\Filament\Admin\Resources\DatabaseHosts\Pages\CreateDatabaseHost;
use App\Filament\Admin\Resources\DatabaseHosts\Pages\EditDatabaseHost;
use App\Filament\Admin\Resources\DatabaseHosts\RelationManagers\DatabasesRelationManager;
use App\Filament\Admin\Resources\Mounts\Pages\ListMounts;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Nodes\RelationManagers\AllocationsRelationManager;
use App\Filament\Admin\Resources\Roles\Pages\CreateRole;
use App\Filament\Admin\Resources\Roles\Pages\EditRole;
use App\Filament\Admin\Resources\Webhooks\Pages\CreateWebhookConfiguration;
use App\Filament\Admin\Resources\Webhooks\Pages\EditWebhookConfiguration;
use App\Models\Allocation;
use App\Models\ApiKey;
use App\Models\BackupHost;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\WebhookConfiguration;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
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

// Same trick as DatabaseHostCrudTest: the host services confirm access via
// getPdo(), so fake the remote connection but keep the panel's own.
function fakeRemoteDbForActivityTests(): void
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getPdo')->andReturn(Mockery::mock(PDO::class));

    $manager = Mockery::mock(app('db'));
    $manager->shouldReceive('build')->andReturn($connection);
    DB::swap($manager);
    app()->instance('db', $manager);
}

it('logs role create', function () {
    livewire(CreateRole::class)
        ->fillForm(['name' => 'Audited Role', 'user_list' => [RolePermissionModels::User->viewAny()]])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::findByName('Audited Role');
    $this->assertActivityFor('role:create', $this->admin, $role);
});

it('logs role rename with a diff', function () {
    $role = Role::factory()->create(['name' => 'Old Name', 'guard_name' => 'web']);

    livewire(EditRole::class, ['record' => $role->getKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertActivityFor('role:update', $this->admin, $role);
});

it('logs a permissions-only role change', function () {
    $role = Role::factory()->create(['name' => 'Static Name', 'guard_name' => 'web']);

    livewire(EditRole::class, ['record' => $role->getKey()])
        ->fillForm(['user_list' => [RolePermissionModels::User->viewAny()]])
        ->call('save')
        ->assertHasNoFormErrors();

    Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $e) {
        if (!$e->is('role:update')) {
            return false;
        }
        $changes = $e->model->properties['changes'];

        return in_array(RolePermissionModels::User->viewAny(), $changes['permissions']['new']);
    });
});

it('logs role delete', function () {
    $role = Role::factory()->create(['name' => 'Doomed Role', 'guard_name' => 'web']);

    livewire(EditRole::class, ['record' => $role->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertActivityFor('role:delete', $this->admin, $role);
});

it('logs api key create and delete', function () {
    livewire(CreateApiKey::class)
        ->fillForm(['memo' => 'audited key'])
        ->call('create')
        ->assertHasNoFormErrors();

    $apiKey = ApiKey::query()->where('memo', 'audited key')->firstOrFail();
    $this->assertActivityFor('apiKey:create', $this->admin, $apiKey);

    livewire(ListApiKeys::class)
        ->callAction(TestAction::make('delete')->table($apiKey));

    $this->assertActivityFor('apiKey:delete', $this->admin, $apiKey);
});

it('logs backup host create, update, and delete with secrets masked', function () {
    livewire(CreateBackupHost::class)
        ->fillForm([
            'name' => 'S3 Host',
            'schema' => 's3',
            'configuration.region' => 'us-east-1',
            'configuration.key' => 'test-key',
            'configuration.secret' => 'test-secret',
            'configuration.bucket' => 'test-bucket',
            'configuration.endpoint' => 'https://s3.example.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $backupHost = BackupHost::query()->where('name', 'S3 Host')->firstOrFail();
    $this->assertActivityFor('backupHost:create', $this->admin, $backupHost);
    Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $e) {
        return $e->is('backupHost:create') && !str_contains(json_encode($e->model->properties), 'test-secret');
    });

    // The delete action hides when only one backup host exists.
    $other = BackupHost::factory()->create();

    livewire(EditBackupHost::class, ['record' => $backupHost->getKey()])
        ->fillForm(['name' => 'Renamed Host'])
        ->call('save')
        ->assertHasNoFormErrors();
    $this->assertActivityFor('backupHost:update', $this->admin, $backupHost);

    livewire(EditBackupHost::class, ['record' => $backupHost->getKey()])
        ->callAction(DeleteAction::class);
    $this->assertActivityFor('backupHost:delete', $this->admin, $backupHost);
});

it('logs database host create, update, and delete', function () {
    fakeRemoteDbForActivityTests();

    livewire(CreateDatabaseHost::class)
        ->fillForm([
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'audited-mysql',
            'username' => 'pelicanuser',
            'password' => 'secret1234',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $databaseHost = DatabaseHost::query()->where('name', 'audited-mysql')->firstOrFail();
    $this->assertActivityFor('databaseHost:create', $this->admin, $databaseHost);

    livewire(EditDatabaseHost::class, ['record' => $databaseHost->getKey()])
        ->fillForm(['name' => 'renamed-mysql'])
        ->call('save')
        ->assertHasNoFormErrors();
    $this->assertActivityFor('databaseHost:update', $this->admin, $databaseHost);

    livewire(EditDatabaseHost::class, ['record' => $databaseHost->getKey()])
        ->callAction(DeleteAction::class);
    $this->assertActivityFor('databaseHost:delete', $this->admin, $databaseHost);
});

it('logs webhook create, update, and delete', function () {
    livewire(CreateWebhookConfiguration::class)
        ->fillForm([
            'name' => 'Notifier',
            'description' => 'Notifies on new servers',
            'endpoint' => 'https://example.com/hook',
            'events' => ['eloquent.created: ' . Server::class],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $webhook = WebhookConfiguration::query()->where('endpoint', 'https://example.com/hook')->firstOrFail();
    $this->assertActivityFor('webhook:create', $this->admin, $webhook);

    livewire(EditWebhookConfiguration::class, ['record' => $webhook->getKey()])
        ->fillForm(['name' => 'Renamed Hook'])
        ->call('save')
        ->assertHasNoFormErrors();
    $this->assertActivityFor('webhook:update', $this->admin, $webhook);

    livewire(EditWebhookConfiguration::class, ['record' => $webhook->getKey()])
        ->callAction(DeleteAction::class);
    $this->assertActivityFor('webhook:delete', $this->admin, $webhook);
});

it('logs allocation delete from the node relation manager', function () {
    $node = Node::factory()->create();
    $allocation = Allocation::factory()->create(['node_id' => $node->id, 'server_id' => null]);

    livewire(AllocationsRelationManager::class, ['ownerRecord' => $node, 'pageClass' => EditNode::class])
        ->callAction(TestAction::make('delete')->table($allocation));

    $this->assertActivityFor('allocation:delete', $this->admin, $allocation);
});

it('logs database delete from the database host relation manager', function () {
    $server = Server::factory()->withNode()->create();
    $databaseHost = DatabaseHost::factory()->create();
    $database = Database::factory()->create(['database_host_id' => $databaseHost->id, 'server_id' => $server->id]);

    livewire(DatabasesRelationManager::class, ['ownerRecord' => $databaseHost, 'pageClass' => EditDatabaseHost::class])
        ->callAction(TestAction::make('delete')->table($database));

    $this->assertActivityFor('database:delete', $this->admin, $database);
});

it('logs one event per record on bulk delete', function () {
    $mounts = collect(['One', 'Two'])->map(fn ($name) => Mount::query()->create([
        'uuid' => Str::uuid()->toString(),
        'name' => "Bulk Mount $name",
        'source' => "/mnt/bulk-$name",
        'target' => "/srv/bulk-$name",
        'read_only' => false,
        'user_mountable' => false,
    ]));

    livewire(ListMounts::class)
        ->selectTableRecords($mounts->pluck('id')->all())
        ->callAction(TestAction::make('exclude_bulk_delete')->table()->bulk());

    $this->assertDatabaseMissing('mounts', ['id' => $mounts[0]->id]);
    $mounts->each(fn (Mount $mount) => $this->assertActivityFor('mount:delete', $this->admin, $mount));
});
