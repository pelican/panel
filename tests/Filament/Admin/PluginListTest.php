<?php

use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Models\Node;
use App\Models\Role;
use App\Models\WingsPlugin;
use App\Repositories\Daemon\DaemonPluginRepository;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;

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

/**
 * Render every tab on the plugin list.
 *
 * The table's columns and actions are closures that Filament evaluates against
 * each row, and it resolves their parameters by name and then by the record's
 * exact class. A parameter that does not resolve is a runtime failure that
 * nothing else catches: the page compiles, static analysis passes, and the page
 * then 500s when somebody opens it. Rendering each tab here is what turns that
 * into a test failure.
 */
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
