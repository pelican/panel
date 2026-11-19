<?php

use App\Enums\EggFormat;
use App\Enums\RolePermissionModels;
use App\Exceptions\Service\InvalidFileUploadException;
use App\Filament\Admin\Resources\BackupHosts\Pages\EditBackupHost;
use App\Filament\Admin\Resources\BackupHosts\RelationManagers\BackupsRelationManager as BackupHostBackupsRelationManager;
use App\Filament\Admin\Resources\Backups\BackupResource;
use App\Filament\Admin\Resources\Backups\Pages\ListBackups;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Nodes\Pages\ViewNode;
use App\Filament\Admin\Resources\Nodes\RelationManagers\BackupsRelationManager as NodeBackupsRelationManager;
use App\Filament\Admin\Resources\Servers\Pages\CreateServer;
use App\Filament\Admin\Resources\Servers\ServerResource;
use App\Models\Allocation;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\ServerRestore;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Backups\RestoreSource;
use App\Services\Backups\ServerConfigExporterService;
use App\Services\Eggs\Sharing\EggExporterService;
use App\Services\Helpers\EggHubService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);
});
afterEach(fn () => Filament::setCurrentPanel(null));

/**
 * A kept backup of a deleted server, with its configuration.
 */
function keptBackup(Node $node, Egg $egg, int $port): Backup
{
    return Backup::factory()->create([
        'server_id' => null,
        'server_uuid' => fake()->uuid(),
        'node_id' => $node->id,
        'backup_host_id' => BackupHost::factory()->create()->id,
        'pelican_config' => [
            'meta' => ['version' => ServerConfigExporterService::VERSION],
            'server' => ['name' => 'restored-server', 'description' => 'from a backup'],
            'egg' => ['uuid' => $egg->uuid, 'name' => $egg->name],
            'image' => 'ghcr.io/pelican-eggs/yolks:java_21',
            'startup' => 'java -jar restored.jar',
            'limits' => ['memory' => 1024, 'swap' => 0, 'disk' => 4096, 'io' => 500, 'cpu' => 100, 'threads' => null, 'oom_killer' => false],
            'feature_limits' => ['databases' => 1, 'allocations' => 2, 'backups' => 5],
            'allocations' => ['default' => ['ip' => '127.0.0.1', 'ip_alias' => null, 'port' => $port, 'notes' => null], 'additional' => []],
            'variables' => [],
            'docker_labels' => [],
            'mounts' => [],
        ],
    ]);
}

it('fills the create server wizard from a backup and records the restore', function () {
    $daemon = Mockery::mock(DaemonServerRepository::class);
    $daemon->expects('setServer')->andReturnSelf();
    // The server is started after the restore, not after the install.
    $daemon->expects('create')->with(false)->andReturnUndefined();
    $this->swap(DaemonServerRepository::class, $daemon);

    $node = Node::factory()->create();
    $allocation = Allocation::factory()->create(['node_id' => $node->id, 'server_id' => null, 'ip' => '127.0.0.1', 'port' => 25599]);
    $egg = Egg::factory()->create();
    $backup = keptBackup($node, $egg, 25599);

    session()->put('last_utilized_node', $node->id);
    session()->put('restore_source.abc', RestoreSource::backup($backup)->toArray());

    $component = Livewire::withQueryParams(['restore' => 'abc'])
        ->test(CreateServer::class)
        ->assertFormSet([
            'name' => 'restored-server',
            'egg_id' => $egg->id,
            'memory' => 1024,
            'disk' => 4096,
            'backup_limit' => 5,
            'image' => 'ghcr.io/pelican-eggs/yolks:java_21',
            'startup' => 'java -jar restored.jar',
            'allocation_id' => $allocation->id,
            'skip_scripts' => true,
        ])
        ->fillForm(['owner_id' => $this->admin->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $server = Server::query()->where('name', 'restored-server')->sole();
    $restore = ServerRestore::query()->sole();

    expect($server->allocation_id)->toBe($allocation->id)
        ->and($server->skip_scripts)->toBeTrue()
        ->and($restore->server_id)->toBe($server->id)
        ->and($restore->backup_id)->toBe($backup->id)
        ->and($restore->status)->toBe(ServerRestore::STATUS_PENDING)
        ->and($restore->start_on_completion)->toBeTrue()
        ->and(session()->has('restore_source.abc'))->toBeFalse();

    // The new server opens on its edit page, not the view page.
    $component->assertRedirect(ServerResource::getUrl('edit', ['record' => $server]));
});

it('lists the addresses that are not on the chosen node', function () {
    $node = Node::factory()->create();
    $egg = Egg::factory()->create();
    $backup = keptBackup($node, $egg, 25598);

    session()->put('last_utilized_node', $node->id);
    session()->put('restore_source.xyz', RestoreSource::backup($backup)->toArray());

    Livewire::withQueryParams(['restore' => 'xyz'])
        ->test(CreateServer::class)
        ->assertFormSet(['allocation_id' => null])
        ->assertSet('restoreMissingAllocations', ['127.0.0.1:25598']);
});

it('only creates missing allocations on nodes the administrator can access', function () {
    Http::fake();
    $visibleNode = Node::factory()->create();
    $hiddenNode = Node::factory()->create();
    // 0.0.0.0 is an address of every node, so the backup's address can be created on either.
    $backup = keptBackup($visibleNode, Egg::factory()->create(), 25640);
    $config = $backup->pelican_config;
    $config['allocations']['default']['ip'] = '0.0.0.0';
    $backup->update(['pelican_config' => $config]);

    $role = Role::query()->create(['name' => 'Node admin', 'guard_name' => 'web']);
    $role->givePermissionTo([
        Permission::findOrCreate(RolePermissionModels::Server->viewAny(), 'web'),
        Permission::findOrCreate(RolePermissionModels::Server->create(), 'web'),
        Permission::findOrCreate(RolePermissionModels::Allocation->create(), 'web'),
    ]);
    $role->nodes()->sync([$visibleNode->id]);
    [$nodeAdmin] = generateTestAccount();
    $nodeAdmin->syncRoles($role);
    $this->actingAs($nodeAdmin);

    session()->put('restore_source.scoped', RestoreSource::backup($backup)->toArray());
    $page = Livewire::withQueryParams(['restore' => 'scoped'])->test(CreateServer::class)
        ->assertSet('restoreMissingAllocations', ['0.0.0.0:25640']);

    // The testing helpers do not resolve actions nested in the wizard's steps, so the
    // action is taken from the form.
    $action = fn () => $page->instance()->form
        ->getComponent(fn ($component) => $component->getKey(isAbsolute: false) === 'restore_allocation_actions', withHidden: true)
        ->getChildSchema()->getComponents(withHidden: true)[0];

    expect($action()->getName())->toBe('create_missing_allocations')
        ->and($action()->isVisible())->toBeTrue();

    // The node select only offers accessible nodes, but the form state can be set to any.
    $page->set('data.node_id', (string) $hiddenNode->id);

    expect($action()->isVisible())->toBeFalse();

    expect(Allocation::query()->where('node_id', $hiddenNode->id)->exists())->toBeFalse();
});

it('only creates allocations from the wizard on nodes the administrator can access', function () {
    Http::fake();
    $visibleNode = Node::factory()->create();
    $hiddenNode = Node::factory()->create();

    $role = Role::query()->create(['name' => 'Node admin', 'guard_name' => 'web']);
    $role->givePermissionTo([
        Permission::findOrCreate(RolePermissionModels::Server->viewAny(), 'web'),
        Permission::findOrCreate(RolePermissionModels::Server->create(), 'web'),
        Permission::findOrCreate(RolePermissionModels::Allocation->create(), 'web'),
    ]);
    $role->nodes()->sync([$visibleNode->id]);
    [$nodeAdmin] = generateTestAccount();
    $nodeAdmin->syncRoles($role);
    $this->actingAs($nodeAdmin);

    $page = Livewire::test(CreateServer::class);

    // The testing helpers do not resolve actions nested in the wizard's steps, so the
    // allocation select's create callback is called as its action would.
    $createAllocation = function (int $port) use ($page) {
        $select = $page->instance()->form->getComponent(fn ($component) => $component instanceof Select && $component->getName() === 'allocation_id', withHidden: true);

        return $select->evaluate($select->getCreateOptionUsing(), ['data' => ['allocation_ip' => '0.0.0.0', 'allocation_ports' => [(string) $port]]]);
    };

    $page->set('data.node_id', (string) $visibleNode->id);
    expect(Allocation::query()->find($createAllocation(25650))?->node_id)->toBe($visibleNode->id);

    // The node select only offers accessible nodes, but the form state can be set to any.
    $page->set('data.node_id', (string) $hiddenNode->id);
    expect(fn () => $createAllocation(25651))->toThrow(ModelNotFoundException::class)
        ->and(Allocation::query()->where('node_id', $hiddenNode->id)->exists())->toBeFalse();
});

it('shows kept backups to administrators who can create servers', function () {
    $node = Node::factory()->create();
    $backup = keptBackup($node, Egg::factory()->create(), 25597);

    livewire(ListBackups::class)
        ->assertCanSeeTableRecords([$backup]);

    $role = Role::query()->create(['name' => 'Viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Server->viewAny(), 'web'));
    [$viewer] = generateTestAccount();
    $viewer->syncRoles($role);
    $this->actingAs($viewer);

    livewire(ListBackups::class)->assertForbidden();
});

it('lets administrators unlock a locked backup of a deleted server', function () {
    $backup = keptBackup(Node::factory()->create(), Egg::factory()->create(), 25598);
    $backup->update(['is_locked' => true]);
    $unlocked = keptBackup(Node::factory()->create(), Egg::factory()->create(), 25599);

    livewire(ListBackups::class)
        ->assertTableActionHidden('unlock', $unlocked)
        ->callTableAction('unlock', $backup);

    expect($backup->refresh()->is_locked)->toBeFalse();
});

it('only shows admins the backups on the nodes they can access', function () {
    $visibleNode = Node::factory()->create();
    $hiddenNode = Node::factory()->create();
    $visible = keptBackup($visibleNode, Egg::factory()->create(), 25596);
    $hidden = keptBackup($hiddenNode, Egg::factory()->create(), 25595);

    $role = Role::query()->create(['name' => 'Node admin', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Server->create(), 'web'));
    $role->nodes()->sync([$visibleNode->id]);
    [$nodeAdmin] = generateTestAccount();
    $nodeAdmin->syncRoles($role);
    $this->actingAs($nodeAdmin);

    livewire(ListBackups::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);

    expect(Backup::query()->accessibleBy($nodeAdmin)->pluck('id')->all())->toBe([$visible->id])
        ->and(Backup::query()->accessibleBy($this->admin)->count())->toBe(2);
});

it('starts the wizard on the backup\'s node', function () {
    $backupNode = Node::factory()->create();
    $otherNode = Node::factory()->create();
    $allocation = Allocation::factory()->create(['node_id' => $backupNode->id, 'server_id' => null, 'ip' => '127.0.0.1', 'port' => 25594]);
    $backup = keptBackup($backupNode, Egg::factory()->create(), 25594);

    session()->put('last_utilized_node', $otherNode->id);
    session()->put('restore_source.node', RestoreSource::backup($backup)->toArray());

    Livewire::withQueryParams(['restore' => 'node'])
        ->test(CreateServer::class)
        ->assertFormSet(['node_id' => $backupNode->id, 'allocation_id' => $allocation->id])
        ->assertSet('restoreMissingAllocations', []);
});

it('refuses links to internal addresses as a restore source', function () {
    livewire(ListBackups::class)
        ->callAction(TestAction::make('create_from_source')->table(), ['type' => RestoreSource::TYPE_URL, 'url' => 'http://10.0.0.5/backup.tar.gz'])
        ->assertHasFormErrors(['url']);

    expect(session()->get('restore_source'))->toBeEmpty();
});

it('does not create a server restored from a link to an internal address', function () {
    $daemon = Mockery::mock(DaemonServerRepository::class);
    $daemon->shouldNotReceive('create');
    $this->swap(DaemonServerRepository::class, $daemon);

    $node = Node::factory()->create();
    $allocation = Allocation::factory()->create(['node_id' => $node->id, 'server_id' => null]);
    $egg = Egg::factory()->create();

    session()->put('last_utilized_node', $node->id);
    // Stored by the action while the link was public, it no longer is when the server is created.
    session()->put('restore_source.link', RestoreSource::url('http://127.0.0.1/backup.tar.gz')->toArray());

    Livewire::withQueryParams(['restore' => 'link'])
        ->test(CreateServer::class)
        ->fillForm([
            'name' => 'from-a-link',
            'owner_id' => $this->admin->id,
            'node_id' => $node->id,
            'allocation_id' => $allocation->id,
            'egg_id' => $egg->id,
            'image' => 'ghcr.io/pelican-eggs/yolks:java_21',
            'startup' => 'java -jar server.jar',
        ])
        ->call('create')
        ->assertNotified(trans('admin/server.notifications.create_failed'));

    expect(Server::query()->where('name', 'from-a-link')->exists())->toBeFalse()
        ->and(ServerRestore::query()->exists())->toBeFalse();
});

it('shows the restored variables and keeps the additional allocations in the wizard', function () {
    $daemon = Mockery::mock(DaemonServerRepository::class);
    $daemon->expects('setServer')->andReturnSelf();
    $daemon->expects('create')->andReturnUndefined();
    $this->swap(DaemonServerRepository::class, $daemon);

    $node = Node::factory()->create();
    $primary = Allocation::factory()->create(['node_id' => $node->id, 'server_id' => null, 'ip' => '127.0.0.1', 'port' => 25610]);
    $extra = Allocation::factory()->create(['node_id' => $node->id, 'server_id' => null, 'ip' => '127.0.0.1', 'port' => 25611]);
    $egg = Egg::factory()->create();
    EggVariable::factory()->create(['egg_id' => $egg->id, 'env_variable' => 'GREETING', 'default_value' => 'hello', 'rules' => ['required', 'string'], 'user_viewable' => true, 'user_editable' => true]);

    $backup = keptBackup($node, $egg, 25610);
    $config = $backup->pelican_config;
    $config['allocations']['additional'] = [['ip' => '127.0.0.1', 'ip_alias' => null, 'port' => 25611, 'notes' => null]];
    $config['variables'] = ['GREETING' => 'from-the-backup'];
    $config['limits']['memory'] = 0;
    $backup->update(['pelican_config' => $config]);

    session()->put('last_utilized_node', $node->id);
    session()->put('restore_source.vars', RestoreSource::backup($backup)->toArray());

    $page = Livewire::withQueryParams(['restore' => 'vars'])->test(CreateServer::class);

    $data = $page->get('data');
    expect(array_values($data['server_variables']))->toHaveCount(1)
        ->and(array_values($data['server_variables'])[0]['variable_value'])->toBe('from-the-backup')
        ->and(array_column(array_values($data['allocation_additional']), 'extra_allocations'))->toBe([$extra->id])
        // Toggle buttons are keyed 0 and 1, so a prefilled false has to be 0 to be shown as selected.
        ->and($data['unlimited_mem'])->toEqual(1)
        ->and($data['oom_killer'])->toEqual(0)->not->toBe('');

    $page->fillForm(['owner_id' => $this->admin->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $server = Server::query()->where('name', 'restored-server')->sole();
    expect($server->allocation_id)->toBe($primary->id)
        ->and($server->allocations()->pluck('id')->sort()->values()->all())->toBe([$primary->id, $extra->id])
        ->and($server->variables()->where('env_variable', 'GREETING')->first()?->server_value)->toBe('from-the-backup');
});

it('shows restored boolean variables as the toggles their values are', function () {
    $daemon = Mockery::mock(DaemonServerRepository::class);
    $daemon->expects('setServer')->andReturnSelf();
    $daemon->expects('create')->andReturnUndefined();
    $this->swap(DaemonServerRepository::class, $daemon);

    $node = Node::factory()->create();
    Allocation::factory()->create(['node_id' => $node->id, 'server_id' => null, 'ip' => '127.0.0.1', 'port' => 25630]);
    $egg = Egg::factory()->create();
    EggVariable::factory()->create(['egg_id' => $egg->id, 'env_variable' => 'AUTO_UPDATE', 'default_value' => '1', 'rules' => ['boolean'], 'user_viewable' => true, 'user_editable' => true, 'sort' => 1]);
    EggVariable::factory()->create(['egg_id' => $egg->id, 'env_variable' => 'ALLOW_OP', 'default_value' => '0', 'rules' => ['boolean'], 'user_viewable' => true, 'user_editable' => true, 'sort' => 2]);

    $backup = keptBackup($node, $egg, 25630);
    $config = $backup->pelican_config;
    $config['variables'] = ['AUTO_UPDATE' => '0', 'ALLOW_OP' => '1'];
    $backup->update(['pelican_config' => $config]);

    session()->put('last_utilized_node', $node->id);
    session()->put('restore_source.toggles', RestoreSource::backup($backup)->toArray());

    $page = Livewire::withQueryParams(['restore' => 'toggles'])->test(CreateServer::class);

    // A toggle reads "0" as on, so the values are the 0 and 1 the field's boolean cast gives.
    $values = collect($page->get('data.server_variables'))->pluck('variable_value', 'env_variable');
    expect($values->all())->toBe(['AUTO_UPDATE' => 0, 'ALLOW_OP' => 1]);

    $page->fillForm(['owner_id' => $this->admin->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $server = Server::query()->where('name', 'restored-server')->sole();
    expect($server->variables()->orderBy('egg_variables.sort')->pluck('server_value', 'env_variable')->all())->toBe(['AUTO_UPDATE' => '0', 'ALLOW_OP' => '1']);
});

it('lists the variables that are not in the backup and the required ones without a value', function () {
    $node = Node::factory()->create();
    $egg = Egg::factory()->create();
    EggVariable::factory()->create(['egg_id' => $egg->id, 'name' => 'Greeting', 'env_variable' => 'GREETING', 'default_value' => 'hello', 'rules' => ['required', 'string'], 'user_viewable' => true, 'user_editable' => true, 'sort' => 1]);
    EggVariable::factory()->create(['egg_id' => $egg->id, 'name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'default_value' => '20', 'rules' => ['required', 'integer'], 'user_viewable' => false, 'user_editable' => false, 'sort' => 2]);
    EggVariable::factory()->create(['egg_id' => $egg->id, 'name' => 'License Key', 'env_variable' => 'LICENSE_KEY', 'default_value' => '', 'rules' => ['required', 'string'], 'user_viewable' => false, 'user_editable' => false, 'sort' => 3]);

    $backup = keptBackup($node, $egg, 25620);
    $config = $backup->pelican_config;
    $config['variables'] = ['GREETING' => 'from-the-backup'];
    $backup->update(['pelican_config' => $config]);

    session()->put('last_utilized_node', $node->id);
    session()->put('restore_source.defaults', RestoreSource::backup($backup)->toArray());

    $page = Livewire::withQueryParams(['restore' => 'defaults'])->test(CreateServer::class);

    expect($page->get('restoreDefaultedVariables'))->toBe([
        ['name' => 'Max Players', 'env_variable' => 'MAX_PLAYERS', 'required_empty' => false],
        ['name' => 'License Key', 'env_variable' => 'LICENSE_KEY', 'required_empty' => true],
    ]);

    $page->assertSeeText(trans('admin/backup.restore.defaulted_variables'))
        ->assertSeeText('Max Players (MAX_PLAYERS), License Key (LICENSE_KEY)')
        ->assertSeeText(trans('admin/backup.restore.required_variables'));

    // Another egg has other variables, so the list no longer applies.
    $page->set('data.egg_id', Egg::factory()->create()->id)
        ->assertDontSeeText(trans('admin/backup.restore.defaulted_variables'))
        ->assertDontSeeText(trans('admin/backup.restore.required_variables'));
});

it('does not list variables when the backup has all of them', function () {
    $node = Node::factory()->create();
    $egg = Egg::factory()->create();
    EggVariable::factory()->create(['egg_id' => $egg->id, 'env_variable' => 'GREETING', 'default_value' => 'hello', 'rules' => ['required', 'string'], 'user_viewable' => true, 'user_editable' => true]);

    $backup = keptBackup($node, $egg, 25621);
    $config = $backup->pelican_config;
    $config['variables'] = ['GREETING' => 'from-the-backup'];
    $backup->update(['pelican_config' => $config]);

    session()->put('last_utilized_node', $node->id);
    session()->put('restore_source.complete', RestoreSource::backup($backup)->toArray());

    Livewire::withQueryParams(['restore' => 'complete'])->test(CreateServer::class)
        ->assertSet('restoreDefaultedVariables', [])
        ->assertDontSeeText(trans('admin/backup.restore.defaulted_variables'));
});

it('lets administrators download a kept backup', function () {
    $node = Node::factory()->create();
    $backup = keptBackup($node, Egg::factory()->create(), 25596);

    livewire(ListBackups::class)
        ->assertActionVisible(TestAction::make('download')->table($backup));
});

it('lists backups under servers in the navigation', function () {
    expect(BackupResource::getNavigationGroup())
        ->toBe(ServerResource::getNavigationGroup())
        ->and(BackupResource::getNavigationSort())
        ->toBeGreaterThan(ServerResource::getNavigationSort() ?? -1);
});

it('lists all backups on a node, including kept ones, on the node page', function () {
    $node = Node::factory()->create();
    $otherNode = Node::factory()->create();
    $server = Server::factory()->withNode($node)->create();
    $serverBackup = Backup::factory()->create([
        'server_id' => $server->id,
        'server_uuid' => $server->uuid,
        'node_id' => $node->id,
        'backup_host_id' => BackupHost::factory()->create()->id,
    ]);
    $kept = keptBackup($node, Egg::factory()->create(), 25593);
    $elsewhere = keptBackup($otherNode, Egg::factory()->create(), 25592);

    livewire(NodeBackupsRelationManager::class, ['ownerRecord' => $node, 'pageClass' => EditNode::class])
        ->assertCanSeeTableRecords([$serverBackup, $kept])
        ->assertCanNotSeeTableRecords([$elsewhere])
        // Backups of servers that still exist are deleted from the server's page.
        ->assertActionHidden(TestAction::make('delete')->table($serverBackup))
        ->assertActionVisible(TestAction::make('delete')->table($kept))
        ->assertActionVisible(TestAction::make('create_from_backup')->table($kept));
});

it('keeps the node backups read-only on the view page', function () {
    $node = Node::factory()->create();
    $kept = keptBackup($node, Egg::factory()->create(), 25591);
    $kept->update(['is_locked' => true]);

    livewire(NodeBackupsRelationManager::class, ['ownerRecord' => $node, 'pageClass' => ViewNode::class])
        ->assertCanSeeTableRecords([$kept])
        ->assertActionHidden(TestAction::make('delete')->table($kept))
        ->assertActionHidden(TestAction::make('unlock')->table($kept))
        ->assertActionHidden(TestAction::make('create_from_backup')->table($kept));
});

it('shows kept backups on the backup host page without linking to their deleted server', function () {
    $kept = keptBackup(Node::factory()->create(), Egg::factory()->create(), 25590);

    livewire(BackupHostBackupsRelationManager::class, ['ownerRecord' => $kept->backupHost, 'pageClass' => EditBackupHost::class])
        ->assertCanSeeTableRecords([$kept])
        ->assertSee('restored-server');
});

/**
 * An administrator who can create servers, limited to the given node.
 */
function restrictedBackupAdmin(Node ...$nodes): User
{
    $role = Role::query()->create(['name' => 'Node admin ' . $nodes[0]->id, 'guard_name' => 'web']);
    $role->givePermissionTo([
        Permission::findOrCreate(RolePermissionModels::Server->viewAny(), 'web'),
        Permission::findOrCreate(RolePermissionModels::Server->create(), 'web'),
    ]);
    $role->nodes()->sync(array_map(fn (Node $node) => $node->id, $nodes));
    [$admin] = generateTestAccount();
    $admin->syncRoles($role);

    return $admin;
}

it('only lets node admins use the S3 backup hosts of their nodes', function () {
    // A node belongs to at most one backup host.
    [$node, $sharedNode] = Node::factory()->count(2)->create();
    $global = BackupHost::factory()->create(['schema' => 's3']);
    $own = BackupHost::factory()->create(['schema' => 's3']);
    $own->nodes()->sync([$node->id]);
    $foreign = BackupHost::factory()->create(['schema' => 's3']);
    $foreign->nodes()->sync([Node::factory()->create()->id]);
    // Also serves a node the administrator cannot access.
    $shared = BackupHost::factory()->create(['schema' => 's3']);
    $shared->nodes()->sync([$sharedNode->id, Node::factory()->create()->id]);

    $nodeAdmin = restrictedBackupAdmin($node, $sharedNode);

    expect(BackupHost::query()->accessibleBy($nodeAdmin)->pluck('id')->sort()->values()->all())->toBe([$global->id, $own->id])
        ->and(BackupHost::query()->accessibleBy($this->admin)->count())->toBe(4)
        ->and(RestoreSource::s3($own, 'snapshots/a.tar.gz')->isAccessibleBy($nodeAdmin))->toBeTrue()
        ->and(RestoreSource::s3($foreign, 'snapshots/a.tar.gz')->isAccessibleBy($nodeAdmin))->toBeFalse()
        ->and(RestoreSource::s3($shared, 'snapshots/a.tar.gz')->isAccessibleBy($nodeAdmin))->toBeFalse()
        ->and(RestoreSource::s3($foreign, 'snapshots/a.tar.gz')->isAccessibleBy($this->admin))->toBeTrue();
});

it('does not let node admins reach another node\'s backup through an S3 key', function () {
    $node = Node::factory()->create();
    $otherNode = Node::factory()->create();
    $host = BackupHost::factory()->create(['schema' => 's3']);
    $nodeAdmin = restrictedBackupAdmin($node);

    $ownBackup = keptBackup($node, Egg::factory()->create(), 25589);
    $foreignBackup = keptBackup($otherNode, Egg::factory()->create(), 25588);
    $foreignServer = Server::factory()->withNode($otherNode)->create();

    expect(RestoreSource::s3($host, "$ownBackup->server_uuid/$ownBackup->uuid.tar.gz")->isAccessibleBy($nodeAdmin))->toBeTrue()
        ->and(RestoreSource::s3($host, "$foreignBackup->server_uuid/$foreignBackup->uuid.tar.gz")->isAccessibleBy($nodeAdmin))->toBeFalse()
        ->and(RestoreSource::s3($host, "/$foreignBackup->server_uuid/$foreignBackup->uuid.tar.gz")->isAccessibleBy($nodeAdmin))->toBeFalse()
        // An object the Panel has no backup of, in the folder of a server on another node.
        ->and(RestoreSource::s3($host, "$foreignServer->uuid/" . fake()->uuid() . '.tar.gz')->isAccessibleBy($nodeAdmin))->toBeFalse()
        ->and(RestoreSource::s3($host, 'snapshots/a.tar.gz')->isAccessibleBy($nodeAdmin))->toBeTrue()
        ->and(RestoreSource::s3($host, "$foreignBackup->server_uuid/$foreignBackup->uuid.tar.gz")->isAccessibleBy($this->admin))->toBeTrue()
        ->and(RestoreSource::backup($foreignBackup)->isAccessibleBy($nodeAdmin))->toBeFalse()
        ->and(RestoreSource::backup($ownBackup)->isAccessibleBy($nodeAdmin))->toBeTrue();
});

it('refuses an S3 backup host on another node as a restore source', function () {
    $node = Node::factory()->create();
    $foreign = BackupHost::factory()->create(['schema' => 's3']);
    $foreign->nodes()->sync([Node::factory()->create()->id]);

    $this->actingAs(restrictedBackupAdmin($node));

    livewire(ListBackups::class)
        ->callAction(TestAction::make('create_from_source')->table(), [
            'type' => RestoreSource::TYPE_S3,
            'backup_host_id' => $foreign->id,
            'key' => 'snapshots/a.tar.gz',
        ])
        ->assertNoRedirect();

    expect(session()->get('restore_source'))->toBeEmpty();
});

it('ignores a restore source the administrator may not use', function () {
    $node = Node::factory()->create();
    $foreign = BackupHost::factory()->create(['schema' => 's3']);
    $foreign->nodes()->sync([Node::factory()->create()->id]);

    $this->actingAs(restrictedBackupAdmin($node));
    session()->put('restore_source.foreign', RestoreSource::s3($foreign, 'snapshots/a.tar.gz')->toArray());

    Livewire::withQueryParams(['restore' => 'foreign'])
        ->test(CreateServer::class)
        ->assertSet('restoreSource', null);
});

/**
 * A backup whose egg is only in the Hub: the egg is exported, then deleted from the panel.
 *
 * @return array{0: Backup, 1: string, 2: string} the backup, the egg's uuid and its YAML
 */
function backupWithHubEgg(Node $node): array
{
    $egg = Egg::factory()->create();
    $backup = keptBackup($node, $egg, 25587);
    $yaml = app(EggExporterService::class)->handle($egg->id, EggFormat::YAML);
    $uuid = $egg->uuid;
    $egg->delete();

    return [$backup, $uuid, $yaml];
}

/**
 * The names of the actions on the "egg is missing" notification that was sent, read
 * the way Notification::assertNotified() reads sent notifications.
 *
 * @return string[]
 */
function eggMissingActions(Backup $backup): array
{
    app(NotificationMailbox::class)->flush();
    $notifications = new Notifications();
    $notifications->mount();
    $notifications->pullNotifications();

    $title = trans('admin/backup.restore.egg_missing', ['egg' => $backup->pelican_config['egg']['name']]);
    $notification = $notifications->notifications->first(fn (FilamentNotification $notification) => $notification->getTitle() === $title);
    expect($notification)->not->toBeNull();

    return array_map(fn ($action) => $action->getName(), $notification->getActions());
}

function fakeHubEgg(string $uuid, string $yaml, string $downloadUrl = 'https://hub.pelican.dev/eggs/hub-egg/download'): void
{
    Http::fake([
        'hub.pelican.dev/api/eggs*' => Http::response(['data' => [[
            'slug' => 'hub-egg',
            'name' => 'Hub Egg',
            'uuid' => $uuid,
            'url' => 'https://hub.pelican.dev/api/eggs/hub-egg',
            'download_url' => $downloadUrl,
        ]]]),
        'hub.pelican.dev/eggs/hub-egg/download' => Http::response($yaml, 200, ['Content-Type' => 'application/yaml', 'Content-Disposition' => 'attachment; filename="egg-hub.yaml"']),
    ]);
}

it('offers to install a missing egg from the Hub and fills in its settings', function () {
    config()->set('panel.plugin.hub_url', 'https://hub.pelican.dev');
    $node = Node::factory()->create();
    [$backup, $uuid, $yaml] = backupWithHubEgg($node);
    fakeHubEgg($uuid, $yaml);

    session()->put('last_utilized_node', $node->id);
    session()->put('restore_source.hub', RestoreSource::backup($backup)->toArray());

    $component = Livewire::withQueryParams(['restore' => 'hub'])->test(CreateServer::class);

    expect(eggMissingActions($backup))->toBe(['install_egg_from_hub']);

    $component->call('installEggFromHub');

    $egg = Egg::query()->where('uuid', $uuid)->sole();
    $component->assertFormSet(['egg_id' => $egg->id, 'name' => 'restored-server'])
        ->assertSet('restoreEggId', $egg->id);
});

it('does not offer to install eggs from the Hub without permission to import eggs', function () {
    config()->set('panel.plugin.hub_url', 'https://hub.pelican.dev');
    $node = Node::factory()->create();
    [$backup, $uuid, $yaml] = backupWithHubEgg($node);
    fakeHubEgg($uuid, $yaml);

    $this->actingAs(restrictedBackupAdmin($node));
    session()->put('last_utilized_node', $node->id);
    session()->put('restore_source.hub', RestoreSource::backup($backup)->toArray());

    Livewire::withQueryParams(['restore' => 'hub'])
        ->test(CreateServer::class)
        ->call('installEggFromHub');

    expect(eggMissingActions($backup))->toBe([])
        ->and(Egg::query()->where('uuid', $uuid)->exists())->toBeFalse();
    // The Hub is not even asked.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'hub.pelican.dev'));
});

it('only installs the egg the backup uses from the Hub', function () {
    config()->set('panel.plugin.hub_url', 'https://hub.pelican.dev');
    $uuid = fake()->uuid();
    $other = Egg::factory()->create();
    $otherYaml = app(EggExporterService::class)->handle($other->id, EggFormat::YAML);
    $other->delete();

    // The Hub answers for the uuid with a file of another egg.
    fakeHubEgg($uuid, $otherYaml);
    expect(fn () => app(EggHubService::class)->install($uuid))->toThrow(InvalidFileUploadException::class)
        ->and(Egg::query()->whereIn('uuid', [$uuid, $other->uuid])->exists())->toBeFalse();

});

it('does not follow a Hub egg download link that is not on the Hub', function () {
    config()->set('panel.plugin.hub_url', 'https://hub.pelican.dev');
    $uuid = fake()->uuid();
    fakeHubEgg($uuid, '', 'https://example.com/egg.yaml');

    expect(app(EggHubService::class)->findByUuid($uuid))->toBeNull();
});

it('lets administrators who may import eggs install one from the Hub', function () {
    config()->set('panel.plugin.hub_url', 'https://hub.pelican.dev');
    $node = Node::factory()->create();
    [$backup, $uuid, $yaml] = backupWithHubEgg($node);
    fakeHubEgg($uuid, $yaml);

    $admin = restrictedBackupAdmin($node);
    // Importing eggs is its own permission, creating eggs is not needed.
    $admin->roles()->first()->givePermissionTo(Permission::findOrCreate('import egg', 'web'));
    $this->actingAs($admin->refresh());
    session()->put('last_utilized_node', $node->id);
    session()->put('restore_source.hub', RestoreSource::backup($backup)->toArray());

    $component = Livewire::withQueryParams(['restore' => 'hub'])->test(CreateServer::class);
    expect(eggMissingActions($backup))->toBe(['install_egg_from_hub']);

    $component->call('installEggFromHub');

    expect(Egg::query()->where('uuid', $uuid)->exists())->toBeTrue()
        ->and($admin->can('create egg'))->toBeFalse();
});
