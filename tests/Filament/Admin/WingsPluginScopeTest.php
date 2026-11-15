<?php

use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Models\Node;
use App\Models\Role;
use App\Models\WingsPlugin;
use App\Tests\Traits\FakesWingsPlugins;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

// A Panel admin may only list or act on the plugins of nodes they can access (contract v2, A7).

uses(FakesWingsPlugins::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    cache()->flush();
    WingsPlugin::flush();

    $this->allowed = Node::factory()->create(['name' => 'allowed-node']);
    $this->denied = Node::factory()->create(['name' => 'denied-node']);

    $this->actingAs($this->nodeRestrictedPluginAdmin($this->allowed));
});

afterEach(function () {
    Filament::setCurrentPanel(null);
    cache()->flush();
    WingsPlugin::flush();
});

it('only lists and asks the nodes the admin can access', function () {
    Http::fake(['*/api/plugins' => $this->wingsList([$this->wingsPlugin('trash')])]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertSee('allowed-node')
        ->assertDontSee('denied-node');

    Http::assertNotSent(fn (Request $request) => $this->sentTo($this->denied, $request));
    Http::assertSent(fn (Request $request) => $this->sentTo($this->allowed, $request));
});

it('does not reach another node through a crafted record key', function () {
    Http::fake(['*' => $this->wingsList([$this->wingsPlugin('trash')])]);

    try {
        livewire(ListPlugins::class)
            ->set('activeTab', 'wings')
            ->callTableAction('exclude_disable', "{$this->denied->id}:trash");
    } catch (Throwable) {
        // Refusing the key outright is fine; nothing may reach the node
    }

    Http::assertNotSent(fn (Request $request) => $this->sentTo($this->denied, $request) && $request->method() === 'POST');
});

it('does not reuse rows cached for an admin with more nodes', function () {
    Http::fake(['*/api/plugins' => $this->wingsList([$this->wingsPlugin('trash')])]);

    [$root] = generateTestAccount();
    $root->syncRoles(Role::getRootAdmin());

    // A root admin fills the table and the cache with every node first
    expect(WingsPlugin::scoped($root)->pluck('node_name'))->toContain('denied-node');

    expect(WingsPlugin::scoped()->pluck('node_name')->unique()->values()->all())->toBe(['allowed-node']);
});

it('refuses a plugin of another node even when the record is built by hand', function () {
    Http::fake();

    $plugin = new WingsPlugin();
    $plugin->forceFill(['node_id' => $this->denied->id, 'plugin_id' => 'trash', 'row_type' => WingsPlugin::ROW_PLUGIN]);

    expect(fn () => $plugin->repository())->toThrow(RuntimeException::class)
        ->and(user()->can('update', $plugin))->toBeFalse();

    Http::assertNothingSent();
});

it('shows the row actions to a node-restricted admin with the permission', function () {
    Http::fake(['*/api/plugins' => $this->wingsList([$this->wingsPlugin('trash')])]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertTableActionVisible('exclude_disable', "{$this->allowed->id}:trash");
});
