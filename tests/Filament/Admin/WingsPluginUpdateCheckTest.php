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

// Whether a Wings plugin has an update (contract v3, A2 "update-check"); can_update only says it could be updated.

uses(FakesWingsPlugins::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);

    cache()->flush();
    WingsPlugin::flush();

    $this->node = Node::factory()->create(['name' => 'plugin-node']);

    // A plugin with an update URL; can_update is false unless the node's level allows updating
    $this->updatable = fn (string $id = 'state-log', bool $canUpdate = false): array => $this->wingsPlugin($id, ['update_url' => "https://hub.example.test/$id/update.json"], ['can_update' => $canUpdate]);
});

afterEach(function () {
    Filament::setCurrentPanel(null);
    cache()->flush();
    WingsPlugin::flush();
});

/** @return array<int, Request> the update checks sent */
function wingsUpdateChecks(): array
{
    return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/update-check'))->map(fn (array $pair) => $pair[0])->values()->all();
}

it('checks a plugin for an update at the view level and shows it on the row', function () {
    $this->fakeWingsNode($this->node, [($this->updatable)()], 'view', [
        '*/api/plugins/state-log/update-check' => Http::response(['installed' => '1.0.0', 'latest' => '1.2.0', 'available' => true]),
    ]);

    $component = livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        // can_update is false at "view", yet whether an update exists can still be checked
        ->assertTableActionHidden('exclude_update', "{$this->node->id}:state-log")
        ->assertTableActionVisible('exclude_check_update', "{$this->node->id}:state-log")
        ->callTableAction('exclude_check_update', "{$this->node->id}:state-log");

    $notification = $this->lastNotification();

    expect($notification->getTitle())->toBe(trans('admin/plugin.wings.update_found', ['name' => 'state-log', 'installed' => '1.0.0', 'latest' => '1.2.0']))
        ->and($notification->getBody())->toBe(trans('admin/plugin.wings.update_found_cannot'))
        ->and(wingsUpdateChecks())->toHaveCount(1)
        ->and(wingsUpdateChecks()[0]->method())->toBe('GET')
        ->and(WingsPlugin::scoped()->where('plugin_id', 'state-log')->first()->isUpdateAvailable())->toBeTrue();

    $component->assertSee(trans('admin/plugin.update_available'));
});

it('does not take can_update for an update that exists', function () {
    $this->fakeWingsNode($this->node, [($this->updatable)(canUpdate: true)], 'signed');

    $plugin = WingsPlugin::scoped()->where('plugin_id', 'state-log')->first();

    expect($plugin->canUpdate())->toBeTrue()
        ->and($plugin->isUpdateAvailable())->toBeFalse();

    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/update-check'));
});

it('says when a plugin is up to date, and when its update url could not be read', function (array $answer, string $title) {
    $this->fakeWingsNode($this->node, [($this->updatable)()], 'view', [
        '*/api/plugins/state-log/update-check' => Http::response($answer),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_check_update', "{$this->node->id}:state-log");

    expect($this->lastNotification()->getTitle())->toBe($title)
        ->and(WingsPlugin::scoped()->where('plugin_id', 'state-log')->first()->isUpdateAvailable())->toBeFalse();
})->with([
    'up to date' => [['installed' => '1.2.0', 'latest' => '1.2.0', 'available' => false], 'state-log is up to date (1.2.0).'],
    'fetch failed' => [['installed' => '1.0.0', 'latest' => null, 'available' => false, 'error' => 'GET https://hub.example.test/state-log/update.json: 502'], "Couldn't check state-log for updates"],
]);

it('reads a 404 from the update check as no such plugin', function () {
    $this->fakeWingsNode($this->node, [($this->updatable)()], 'view', [
        '*/api/plugins/state-log/update-check' => $this->wingsError($this->node, 404, 'plugin "state-log" not found'),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_check_update', "{$this->node->id}:state-log");

    $notification = $this->lastNotification();

    expect($notification->getTitle())->toBe(trans('admin/plugin.wings.update_check_failed', ['name' => 'state-log']))
        ->and($notification->getBody())->toBe(trans('admin/plugin.wings.not_found'));
});

it('offers no update check for a plugin without an update url', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('state-log')], 'full');

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertTableActionHidden('exclude_check_update', "{$this->node->id}:state-log");
});

it('checks every plugin with an update url on the admin\'s nodes at once', function () {
    $other = Node::factory()->create(['name' => 'other-node']);

    Http::fake([
        '*/api/plugins/state-log/update-check' => Http::response(['installed' => '1.0.0', 'latest' => '1.2.0', 'available' => true]),
        '*/api/plugins/notify/update-check' => Http::response(['installed' => '2.0.0', 'latest' => '2.0.0', 'available' => false]),
        "*{$this->node->fqdn}*/api/plugins" => $this->wingsList([($this->updatable)(), $this->wingsPlugin('no-url')], 'view'),
        "*{$other->fqdn}*/api/plugins" => $this->wingsList([($this->updatable)('notify')], 'view'),
        '*/api/plugins' => $this->wingsList([]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_check_updates');

    $checked = collect(wingsUpdateChecks())->map(fn (Request $request) => parse_url($request->url(), PHP_URL_HOST) . parse_url($request->url(), PHP_URL_PATH))->sort()->values()->all();

    expect($checked)->toEqualCanonicalizing([
        "{$other->fqdn}/api/plugins/notify/update-check",
        "{$this->node->fqdn}/api/plugins/state-log/update-check",
    ])
        ->and($this->lastNotification()->getTitle())->toBe('1 Wings plugin has an update')
        ->and(WingsPlugin::scoped()->where('plugin_id', 'state-log')->first()->isUpdateAvailable())->toBeTrue()
        ->and(WingsPlugin::scoped()->where('plugin_id', 'notify')->first()->isUpdateAvailable())->toBeFalse();
});
