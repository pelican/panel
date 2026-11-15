<?php

use App\Enums\PluginStatus;
use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Models\Node;
use App\Models\Role;
use App\Models\WingsPlugin;
use App\Tests\Traits\FakesWingsPlugins;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

// How nodes and their plugins become rows (contract v2, L, A3 and A7).

uses(FakesWingsPlugins::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);

    cache()->flush();
    WingsPlugin::flush();
});

afterEach(function () {
    Filament::setCurrentPanel(null);
    cache()->flush();
    WingsPlugin::flush();
});

it('shows a node whose access level forbids listing as not available, with how to raise it', function () {
    $node = Node::factory()->create(['name' => 'locked-node']);

    Http::fake([
        "*{$node->fqdn}*/api/plugins" => $this->wingsForbidden($node, 'off', 'view'),
        '*/api/plugins' => $this->wingsList([]),
    ]);

    $row = WingsPlugin::scoped()->where('node_id', $node->id)->first();

    expect($row->status)->toBe(PluginStatus::Unavailable)
        ->and($row->isPlugin())->toBeFalse()
        ->and($row->error)->toBeNull()
        ->and($row->panel_access)->toBe('off')
        ->and($row->status_message)->toContain('"off"')
        ->and($row->status_message)->toContain('wings plugins panel-access view');

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertSee('locked-node')
        ->assertSee('Not available');
});

it('shows a node whose wings has no plugin api as not available', function () {
    $node = Node::factory()->create(['name' => 'old-wings']);

    Http::fake([
        "*{$node->fqdn}*/api/plugins" => $this->wingsNotFound($node),
        '*/api/plugins' => $this->wingsList([]),
    ]);

    $row = WingsPlugin::scoped()->where('node_id', $node->id)->first();

    expect($row->status)->toBe(PluginStatus::Unavailable)
        ->and($row->error)->toBeNull()
        ->and($row->status_message)->toContain('no plugin API');
});

it('maps a plugin as wings describes it', function () {
    $node = Node::factory()->create();

    $native = $this->wingsPlugin('backup-s3', ['kind' => 'native', 'category' => 'plugin', 'capability' => 'plugin', 'host_access' => []], ['trust' => 'native']);
    unset($native['entrypoint'], $native['url'], $native['update_url'], $native['wings_version']);

    $external = $this->wingsPlugin('state-log', [
        'url' => 'https://github.com/example/state-log',
        'update_url' => 'https://hub.example.test/plugins/state-log/update.json',
        'wings_version' => '1.0.0',
        'host_access' => ['server.logs'],
    ], ['status' => 'disabled', 'enabled' => false, 'trust' => 'changed', 'is_trusted' => false, 'status_message' => 'its files changed', 'can_disable' => false, 'can_uninstall' => true]);

    Http::fake(['*/api/plugins' => $this->wingsList([$native, $external], 'signed')]);

    $rows = WingsPlugin::scoped()->where('node_id', $node->id)->get()->keyBy('plugin_id');

    expect($rows['backup-s3']->kind)->toBe('native')
        ->and($rows['backup-s3']->url)->toBeNull()
        ->and($rows['backup-s3']->wings_version)->toBeNull()
        ->and($rows['backup-s3']->trust)->toBe('native')
        ->and($rows['backup-s3']->status)->toBe(PluginStatus::Enabled)
        ->and($rows['state-log']->status)->toBe(PluginStatus::Disabled)
        ->and($rows['state-log']->status_message)->toBe('its files changed')
        ->and($rows['state-log']->trust)->toBe('changed')
        ->and($rows['state-log']->url)->toBe('https://github.com/example/state-log')
        ->and($rows['state-log']->hostAccessList())->toBe(['server.logs'])
        ->and($rows['state-log']->panel_access)->toBe('signed')
        ->and($rows['state-log']->canDisable())->toBeFalse()
        ->and($rows['state-log']->canUninstall())->toBeTrue();
});

it('shows a status outside enabled, disabled and errored as errored', function (string $status) {
    Node::factory()->create();

    Http::fake(['*/api/plugins' => $this->wingsList([$this->wingsPlugin('odd', meta: ['status' => $status])])]);

    expect(WingsPlugin::scoped()->where('plugin_id', 'odd')->first()->status)->toBe(PluginStatus::Errored);
})->with(['not_installed', 'incompatible', 'crashed', 'quarantined']);

it('lists a plugin with an invalid id as an error row without a plugin id', function () {
    Node::factory()->create();

    Http::fake(['*/api/plugins' => $this->wingsList([$this->wingsPlugin('../etc'), $this->wingsPlugin('fine')])]);

    $rows = WingsPlugin::scoped()->get();

    expect($rows->firstWhere('plugin_id', 'fine'))->not->toBeNull()
        ->and($rows->where('row_type', WingsPlugin::ROW_ERROR)->count())->toBeGreaterThan(0)
        ->and($rows->contains(fn (WingsPlugin $row) => $row->plugin_id === '../etc'))->toBeFalse();
});

it('counts only real plugins in the wings tab badge', function () {
    $node = Node::factory()->create(['name' => 'one-plugin']);
    $locked = Node::factory()->create(['name' => 'locked-node']);

    Http::fake([
        "*{$node->fqdn}*/api/plugins" => $this->wingsList([$this->wingsPlugin('trash')]),
        "*{$locked->fqdn}*/api/plugins" => $this->wingsForbidden($locked, 'off', 'view'),
        '*/api/plugins' => $this->wingsList([]),
    ]);

    $tabs = livewire(ListPlugins::class)->set('activeTab', 'wings')->instance()->getTabs();

    expect($tabs['wings']->getBadge())->toBe('1');
});

it('shows no wings badge count when a node did not answer', function () {
    $node = Node::factory()->create(['name' => 'one-plugin']);
    $down = Node::factory()->create(['name' => 'down-node']);

    Http::fake([
        "*{$node->fqdn}*/api/plugins" => $this->wingsList([$this->wingsPlugin('trash')]),
        "*{$down->fqdn}*/api/plugins" => Http::response('bad gateway', 502, $this->wingsHeaders($down)),
        '*/api/plugins' => $this->wingsList([]),
    ]);

    $tabs = livewire(ListPlugins::class)->set('activeTab', 'wings')->instance()->getTabs();

    expect($tabs['wings']->getBadge())->toBeNull();
});

it('does not ask the nodes for the wings badge on another tab', function () {
    Node::factory()->create();

    Http::fake();

    $tabs = livewire(ListPlugins::class)->set('activeTab', 'all')->instance()->getTabs();

    expect($tabs['wings']->getBadge())->toBeNull();

    Http::assertNothingSent();
});
