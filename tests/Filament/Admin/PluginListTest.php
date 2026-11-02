<?php

use App\Enums\PluginStatus;
use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Models\Node;
use App\Models\Role;
use App\Models\WingsPlugin;
use App\Repositories\Daemon\DaemonPluginRepository;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);

    cache()->forget('wings_plugins.rows');
    WingsPlugin::flush();
});

afterEach(function () {
    Filament::setCurrentPanel(null);
    cache()->forget('wings_plugins.rows');
    WingsPlugin::flush();
});

// Closure parameters are resolved by the record's exact class, which only fails once a tab renders

it('renders every tab on the plugin list', function () {
    $tabs = array_keys(livewire(ListPlugins::class)->instance()->getTabs());

    expect($tabs)->toContain('all')->toContain('wings');

    foreach ($tabs as $tab) {
        livewire(ListPlugins::class)
            ->set('activeTab', $tab)
            ->assertSuccessful();
    }
});

it('renders the wings tab with a plugin reported by a node', function () {
    Node::factory()->create(['name' => 'test-node']);

    // What a node running the plugin subsystem answers with.
    Http::fake([
        '*/api/plugins' => Http::response([
            'enabled' => true,
            'directory' => '/var/lib/pelican/plugins',
            'data' => [[
                'id' => 'trash',
                'name' => 'Trash',
                'author' => 'Joanfo',
                'version' => '1.0.0',
                'description' => 'Moves deleted files into a .trash folder.',
                'category' => 'plugin',
                'url' => '',
                'update_url' => '',
                'api_version' => 1,
                'wings_version' => '^1.0',
                'package' => 'trash',
                'entrypoint' => 'New',
                'settings' => [
                    ['key' => 'retention_days', 'label' => 'Keep for', 'type' => 'number', 'default' => 7],
                ],
                'meta' => [
                    'status' => 'enabled',
                    'status_message' => '',
                    'load_order' => 0,
                    'values' => ['retention_days' => 7],
                    'loaded' => true,
                    'is_compatible' => true,
                    'is_api_supported' => true,
                    'is_trusted' => false,
                    'can_enable' => false,
                    'can_disable' => true,
                    'can_uninstall' => true,
                    'registered' => [
                        'hooks' => ['before_file_action'],
                        'routes' => 2,
                        'jobs' => 1,
                        'config_parsers' => [],
                        'backup_adapter' => '',
                    ],
                ],
            ]],
        ]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertSuccessful()
        // The row, and the Wings-only columns, actually render.
        ->assertSee('Trash')
        ->assertSee('test-node');
});

it('reports a node without plugin support as unsupported', function () {
    $node = Node::factory()->create();

    // A Wings from before plugins existed has no such route. The daemon client
    // validates the User-Agent on any failed response before deciding whether
    // the node is really Wings, so a fake 404 has to carry it or the failure
    // that surfaces is "this is not a Wings node" rather than "this Wings has
    // no plugin support", which is a different branch entirely.
    Http::fake([
        '*/api/plugins' => Http::response('404 page not found', 404, [
            'User-Agent' => "Pelican Wings/vdevelop (id:{$node->daemon_token_id})",
        ]),
    ]);

    $response = (new DaemonPluginRepository())->setNode($node)->getPlugins();

    // Not an error: the node is healthy, it simply has nothing to manage, so
    // it contributes no rows rather than an alarming one.
    expect($response['supported'])->toBeFalse()
        ->and($response['enabled'])->toBeFalse()
        ->and($response['data'])->toBe([]);
});

it('keeps panel plugins working while wings plugins are listed', function () {
    Node::factory()->create();

    Http::fake(['*/api/plugins' => Http::response(['enabled' => true, 'data' => []])]);

    // Both models are Sushi-backed. They used to share one in-memory database,
    // which meant whichever booted first created its table and the other's
    // query failed outright.
    livewire(ListPlugins::class)->set('activeTab', 'wings')->assertSuccessful();
    livewire(ListPlugins::class)->set('activeTab', 'all')->assertSuccessful();
});

it('does not treat a 404 that did not come from wings as an unsupported node', function () {
    $node = Node::factory()->create();

    Http::fake([
        '*/api/plugins' => Http::response('not found', 404, ['User-Agent' => 'nginx']),
    ]);

    (new DaemonPluginRepository())->setNode($node)->getPlugins();
})->throws(Exception::class);

it('lists every node and keeps unreachable ones as error rows', function () {
    $reachable = Node::factory()->create(['name' => 'up-node']);
    $unreachable = Node::factory()->create(['name' => 'down-node']);

    Http::fake([
        "*{$reachable->fqdn}*/api/plugins" => Http::response(['enabled' => true, 'data' => []]),
        "*{$unreachable->fqdn}*/api/plugins" => Http::response('unavailable', 500),
    ]);

    $plugins = WingsPlugin::query()->get();

    expect($plugins->firstWhere('node_name', 'down-node')->error)->not->toBeNull()
        ->and($plugins->firstWhere('node_name', 'up-node'))->toBeNull();
});

it('authorizes wings plugins through their own policy', function () {
    [$user] = generateTestAccount();

    $node = Node::factory()->create();
    $plugin = new WingsPlugin();
    $plugin->node_id = $node->id;

    expect($user->can('update', $plugin))->toBeFalse();

    $role = Role::factory()->create(['name' => 'Plugin Manager', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate('update plugin', 'web'));
    $user->syncRoles($role);

    expect($user->fresh()->can('update', $plugin))->toBeTrue();
});

it('only lets admins manage wings plugins on nodes they can access', function () {
    [$user] = generateTestAccount();

    $allowed = Node::factory()->create(['name' => 'allowed-node']);
    $denied = Node::factory()->create(['name' => 'denied-node']);

    $role = Role::factory()->create(['name' => 'Plugin Manager', 'guard_name' => 'web']);
    $role->givePermissionTo([
        Permission::findOrCreate('viewList plugin', 'web'),
        Permission::findOrCreate('update plugin', 'web'),
    ]);
    $role->nodes()->sync([$allowed->id]);
    $user->syncRoles($role);

    Http::fake(['*/api/plugins' => Http::response(['enabled' => true, 'data' => [['id' => 'trash', 'name' => 'Trash']]])]);

    $this->actingAs($user->fresh());

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertSee('allowed-node')
        ->assertDontSee('denied-node');

    $plugin = new WingsPlugin();
    $plugin->node_id = $denied->id;

    expect($user->fresh()->can('update', $plugin))->toBeFalse();
});

it('keeps listing wings plugins when one of them is malformed', function () {
    Node::factory()->create();

    Http::fake([
        '*/api/plugins' => Http::response(['enabled' => true, 'data' => [
            ['id' => 'broken', 'name' => 'Broken', 'meta' => ['registered' => ['hooks' => 'not-a-list']]],
            ['id' => 'fine', 'name' => 'Fine'],
        ]]),
    ]);

    expect(WingsPlugin::query()->where('plugin_id', 'fine')->exists())->toBeTrue()
        ->and(WingsPlugin::query()->where('plugin_id', 'broken')->exists())->toBeFalse();
});

it('fills wings plugin settings with their declared defaults', function () {
    Node::factory()->create();

    Http::fake([
        '*/api/plugins' => Http::response(['enabled' => true, 'data' => [[
            'id' => 'trash',
            'name' => 'Trash',
            'settings' => [
                ['key' => 'retention_days', 'type' => 'number', 'default' => 7],
                ['key' => 'folder', 'type' => 'string', 'default' => '.trash'],
            ],
            'meta' => ['values' => ['folder' => 'bin']],
        ]]]),
    ]);

    expect(WingsPlugin::query()->where('plugin_id', 'trash')->first()->getSettingsFormData())
        ->toBe(['retention_days' => 7, 'folder' => 'bin']);
});

it('lists a wings plugin that omits its optional fields', function () {
    Node::factory()->create(['name' => 'sparse-node']);

    Http::fake([
        '*/api/plugins' => Http::response(['enabled' => true, 'data' => [['id' => 'sparse', 'name' => 'Sparse']]]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertSuccessful()
        ->assertSee('Sparse');
});

it('shows an offline node as an error row', function () {
    $node = Node::factory()->create(['name' => 'offline-node']);

    Http::fake([
        "*{$node->fqdn}*/api/plugins" => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $row = WingsPlugin::query()->get()->firstWhere('node_name', 'offline-node');

    expect($row)->not->toBeNull()
        ->and($row->error)->toContain('Connection refused');
});

it('reports a wings 404 as unsupported when the node is fetched asynchronously', function () {
    $node = Node::factory()->create(['name' => 'old-node']);

    Http::fake([
        "*{$node->fqdn}*/api/plugins" => Http::response('404 page not found', 404, [
            'User-Agent' => "Pelican Wings/vdevelop (id:{$node->daemon_token_id})",
        ]),
    ]);

    expect(WingsPlugin::query()->get()->firstWhere('node_name', 'old-node'))->toBeNull();
});

it('lists a wings plugin with a status the panel does not know as errored', function () {
    Node::factory()->create(['name' => 'new-node']);

    Http::fake([
        '*/api/plugins' => Http::response(['enabled' => true, 'data' => [[
            'id' => 'odd',
            'name' => 'Odd',
            'meta' => ['status' => 'quarantined'],
        ], [
            'id' => 'malformed',
            'name' => 'Malformed',
            'meta' => ['status' => ['enabled']],
        ]]]),
    ]);

    expect(WingsPlugin::query()->where('plugin_id', 'odd')->first()->status)->toBe(PluginStatus::Errored)
        ->and(WingsPlugin::query()->where('plugin_id', 'malformed')->first()->status)->toBe(PluginStatus::Errored);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertSuccessful()
        ->assertSee('Odd');
});

it('only offers the panel import actions outside the wings tab', function () {
    Http::fake(['*/api/plugins' => Http::response(['enabled' => true, 'data' => []])]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'all')
        ->assertTableActionVisible('import_from_url')
        ->set('activeTab', 'wings')
        ->assertTableActionHidden('import_from_url');
});
