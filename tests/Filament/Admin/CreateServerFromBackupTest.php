<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Backups\Pages\ListBackups;
use App\Filament\Admin\Resources\Servers\Pages\CreateServer;
use App\Models\Allocation;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\ServerRestore;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Backups\RestoreSource;
use App\Services\Backups\ServerConfigExporterService;
use Filament\Facades\Filament;
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

    Livewire::withQueryParams(['restore' => 'abc'])
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
