<?php

use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Models\Node;
use App\Models\Role;
use App\Models\WingsPlugin;
use App\Tests\Traits\FakesWingsPlugins;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

// Two-step install, update and import (contract v3, A4) and the levels they need (L).

uses(FakesWingsPlugins::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);

    cache()->flush();
    WingsPlugin::flush();

    $this->node = Node::factory()->create(['name' => 'plugin-node']);
});

afterEach(function () {
    Filament::setCurrentPanel(null);
    cache()->flush();
    WingsPlugin::flush();
});

/** Whether $request carries an approval token. */
function wingsInstallApproves(Request $request): bool
{
    return array_key_exists('approve', json_decode($request->body(), true) ?: []);
}

/** A component of the mounted action's form. */
function wingsInstallFormComponent(Testable $component, string $key): mixed
{
    $page = $component->instance();

    return $page->getSchema($page->getMountedActionSchemaName())->getComponent(fn ($field) => method_exists($field, 'getName') && $field->getName() === $key, withActions: false, withHidden: true);
}

it('stages an install, shows it, and installs only after approval', function () {
    $this->fakeWingsNode($this->node, [], 'signed', [
        '*/api/plugins/state-log/install' => Http::sequence()
            ->push($this->wingsStagedBody('install', 'state-log', 'tok-install'), 202)
            ->push(['status' => 'installed', 'plugin' => $this->wingsPlugin('state-log')]),
    ]);

    $component = livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/state-log'])
        ->assertHasNoTableActionErrors()
        ->assertActionMounted('approveWingsPlugin')
        ->assertMountedActionModalSee('server.logs')
        ->assertMountedActionModalSee(trans('admin/plugin.wings.signed_yes'));

    // Staged, nothing approved yet
    $installCalls = fn () => Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/api/plugins/state-log/install'));

    expect($installCalls())->toHaveCount(1)
        ->and(json_decode($installCalls()->first()[0]->body(), true))->toBe(['registry' => 'pelican']);

    Http::assertNotSent(fn (Request $request) => wingsInstallApproves($request));

    $component->callMountedAction()
        ->assertNotified(trans('admin/plugin.notifications.installed'));

    expect($installCalls())->toHaveCount(2)
        ->and(json_decode($installCalls()->last()[0]->body(), true))->toBe(['approve' => 'tok-install']);
});

it('offers the plugins of the node\'s registries and installs the picked one from its registry', function () {
    $this->fakeWingsNode($this->node, [], 'signed', [
        '*/api/plugins/available' => $this->wingsAvailable([
            $this->wingsAvailableEntry('state-log', 'acme', ['has_signature' => false]),
            $this->wingsAvailableEntry('notify', 'pelican', ['version' => '2.0.0']),
            $this->wingsAvailableEntry('installed-one', 'pelican', ['installed' => '1.0.0']),
            // Not well formed: left out
            $this->wingsAvailableEntry('../escape', 'pelican'),
            ['name' => 'no-registry', 'version' => '1.0.0', 'has_signature' => true, 'installed' => null],
        ], ['private' => 'GET https://registry.example.test/registry.json: 401 Unauthorized']),
        '*/api/plugins/notify/install' => $this->wingsStaged('install', 'notify', 'tok-notify'),
    ]);

    $component = livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->mountTableAction('wings_install')
        ->setTableActionData(['node' => $this->node->id]);

    $field = wingsInstallFormComponent($component, 'plugin');

    expect($field->getOptions())->toBe([
        '@acme/state-log' => 'state-log 1.2.0 (@acme)',
        '@pelican/notify' => 'notify 2.0.0 (@pelican) · signature',
        '@pelican/installed-one' => 'installed-one 1.2.0 (@pelican) · signature · installed 1.0.0',
    ])
        ->and($field->isOptionDisabled('@pelican/installed-one', 'installed-one'))->toBeTrue()
        ->and($field->isOptionDisabled('@pelican/notify', 'notify'))->toBeFalse();

    // A registry the node couldn't read is shown, and the others still work
    $errors = wingsInstallFormComponent($component, 'registry_errors');

    expect($errors->isVisible())->toBeTrue()
        ->and($errors->getState())->toBe('@private: GET https://registry.example.test/registry.json: 401 Unauthorized');

    $component->setTableActionData(['plugin' => '@pelican/notify'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertActionMounted('approveWingsPlugin');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/plugins/notify/install')
        && json_decode($request->body(), true) === ['registry' => 'pelican']);
});

it('refuses a plugin the node\'s registries do not offer', function () {
    $this->fakeWingsNode($this->node, [], 'signed');

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/not-offered'])
        ->assertHasTableActionErrors(['plugin']);

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('explains a node whose wings cannot list its registries', function () {
    $this->fakeWingsNode($this->node, [], 'signed', [
        '*/api/plugins/available' => $this->wingsError($this->node, 404, 'plugin "available" not found'),
    ]);

    $component = livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->mountTableAction('wings_install')
        ->setTableActionData(['node' => $this->node->id]);

    expect(wingsInstallFormComponent($component, 'registry_errors')->getState())->toBe(trans('admin/plugin.wings.available_unsupported'))
        ->and(wingsInstallFormComponent($component, 'plugin')->getOptions())->toBe([]);
});

it('never approves when the admin closes the confirmation', function () {
    $this->fakeWingsNode($this->node, [], 'signed', [
        '*/api/plugins/state-log/install' => $this->wingsStaged('install', 'state-log', 'tok-install'),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/state-log'])
        ->assertActionMounted('approveWingsPlugin')
        ->unmountAction();

    Http::assertNotSent(fn (Request $request) => wingsInstallApproves($request));
});

it('keeps the approval token on the server', function () {
    $this->fakeWingsNode($this->node, [], 'signed', [
        '*/api/plugins/state-log/install' => $this->wingsStaged('install', 'state-log', 'tok-secret'),
    ]);

    $component = livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/state-log']);

    expect(json_encode($component->instance()->mountedActions))->not->toContain('tok-secret');
});

it('explains why the node refused an approval and stages it again on request', function (int $status, array $error, string $says) {
    $this->fakeWingsNode($this->node, [], 'signed', [
        '*/api/plugins/state-log/install' => Http::sequence()
            ->push($this->wingsStagedBody('install', 'state-log', 'tok-old'), 202)
            ->push($error, $status, $this->wingsHeaders($this->node))
            ->push($this->wingsStagedBody('install', 'state-log', 'tok-new'), 202),
    ]);

    $component = livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/state-log'])
        ->callMountedAction();

    $notification = $this->lastNotification();
    $handle = $this->notificationEventData('restage', $notification);

    expect($notification->getTitle())->toBe(trans('admin/plugin.notifications.install_error'))
        ->and($notification->getBody())->toContain($says)
        ->and($notification->getBody())->toContain(trans('admin/plugin.wings.approval_stage_again'))
        ->and($handle)->toBeString();

    // Staged again from the same registry, and shown for approval again
    $component->dispatch('restage-wings-plugin', handle: $handle)
        ->assertActionMounted('approveWingsPlugin');

    $bodies = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/api/plugins/state-log/install'))
        ->map(fn (array $pair) => json_decode($pair[0]->body(), true))
        ->values()
        ->all();

    expect($bodies)->toBe([['registry' => 'pelican'], ['approve' => 'tok-old'], ['registry' => 'pelican']]);
})->with([
    'level dropped (403)' => [403, ['error' => 'plugins.panel_access is "view" on this node', 'panel_access' => 'view', 'required' => 'signed'], 'access level changed since this was staged'],
    'signature policy (422)' => [422, ['error' => 'signature no longer verifies against a trusted key'], 'signature no longer passes'],
    'installed plugin changed (409)' => [409, ['error' => 'the installed plugin changed since it was staged'], 'the installed plugin changed since it was staged'],
]);

it('asks for the upload again when the node refuses an uploaded archive', function () {
    $this->fakeWingsNode($this->node, [], 'full', [
        '*/api/plugins/import' => Http::sequence()
            ->push($this->wingsStagedBody('import', 'state-log', 'tok-upload'), 202)
            ->push(['error' => 'signature no longer verifies against a trusted key'], 422, $this->wingsHeaders($this->node)),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_import', data: [
            'node' => $this->node->id,
            'source' => 'file',
            'file' => UploadedFile::fake()->createWithContent('state-log.tar.gz', 'archive bytes'),
        ])
        ->callMountedAction();

    $notification = $this->lastNotification();

    expect($notification->getTitle())->toBe(trans('admin/plugin.notifications.import_failed'))
        ->and($notification->getBody())->toContain(trans('admin/plugin.wings.approval_upload_again'))
        ->and($this->notificationEventData('restage', $notification))->toBeNull();
});

it('stages again an import from a url with what it was staged from', function () {
    $this->fakeWingsNode($this->node, [], 'full', [
        '*/api/plugins/import' => Http::sequence()
            ->push($this->wingsStagedBody('import', 'state-log', 'tok-a'), 202)
            ->push(['error' => 'approval token expired'], 409, $this->wingsHeaders($this->node))
            ->push($this->wingsStagedBody('import', 'state-log', 'tok-b'), 202),
    ]);

    $component = livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_import', data: ['node' => $this->node->id, 'source' => 'url', 'url' => 'https://example.test/s.zip', 'signature' => 'c2ln'])
        ->callMountedAction();

    $component->dispatch('restage-wings-plugin', handle: $this->notificationEventData('restage', $this->lastNotification()))
        ->assertActionMounted('approveWingsPlugin');

    $bodies = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/api/plugins/import'))
        ->map(fn (array $pair) => json_decode($pair[0]->body(), true))
        ->values()
        ->all();

    expect($bodies[2])->toBe(['url' => 'https://example.test/s.zip', 'signature' => 'c2ln']);
});

it('stages again only once, and only for the admin who was refused', function () {
    $this->fakeWingsNode($this->node, [], 'signed', [
        '*/api/plugins/state-log/install' => Http::sequence()
            ->push($this->wingsStagedBody('install', 'state-log', 'tok-old'), 202)
            ->push(['error' => 'approval token expired'], 409, $this->wingsHeaders($this->node))
            ->push($this->wingsStagedBody('install', 'state-log', 'tok-new'), 202),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/state-log'])
        ->callMountedAction();

    $handle = $this->notificationEventData('restage', $this->lastNotification());

    [$other] = generateTestAccount();
    $other->syncRoles(Role::getRootAdmin());
    $this->actingAs($other);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->dispatch('restage-wings-plugin', handle: $handle)
        ->assertActionNotMounted('approveWingsPlugin')
        ->assertNotified(trans('admin/plugin.wings.approval_missing'));

    $this->actingAs($this->admin);

    livewire(ListPlugins::class)->set('activeTab', 'wings')->dispatch('restage-wings-plugin', handle: $handle)->assertActionMounted('approveWingsPlugin');
    livewire(ListPlugins::class)->set('activeTab', 'wings')->dispatch('restage-wings-plugin', handle: $handle)->assertActionNotMounted('approveWingsPlugin');

    expect(Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/install')))->toHaveCount(3);
});

it('explains too many installs waiting on the node (429) and asks nothing to approve', function () {
    $this->fakeWingsNode($this->node, [], 'signed', [
        '*/api/plugins/state-log/install' => $this->wingsError($this->node, 429, 'too many installs are waiting for approval'),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/state-log'])
        ->assertActionNotMounted('approveWingsPlugin')
        ->assertNotified(trans('admin/plugin.notifications.install_error'));
});

it('refuses an approval handle that is not the admin\'s', function () {
    $this->fakeWingsNode($this->node, []);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->mountAction('approveWingsPlugin', ['approval' => str_repeat('a', 40)])
        ->callMountedAction()
        ->assertNotified(trans('admin/plugin.wings.approval_missing'));

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('offers install only on nodes at signed or higher, and import only at full', function (string $level, bool $install, bool $import) {
    $this->fakeWingsNode($this->node, [], $level);

    $component = livewire(ListPlugins::class)->set('activeTab', 'wings');

    $install ? $component->assertTableActionVisible('wings_install') : $component->assertTableActionHidden('wings_install');
    $import ? $component->assertTableActionVisible('wings_import') : $component->assertTableActionHidden('wings_import');
})->with([
    'view' => ['view', false, false],
    'signed' => ['signed', true, false],
    'full' => ['full', true, true],
]);

it('does not stage on a node below the level, even when its id is sent', function () {
    $other = Node::factory()->create(['name' => 'signed-node']);

    Http::fake([
        "*{$other->fqdn}*/api/plugins" => $this->wingsList([], 'signed'),
        "*{$this->node->fqdn}*/api/plugins" => $this->wingsList([], 'view'),
        '*/api/plugins' => $this->wingsList([]),
        '*' => $this->wingsStaged('install', 'state-log', 'tok'),
    ]);

    try {
        livewire(ListPlugins::class)
            ->set('activeTab', 'wings')
            ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/state-log']);
    } catch (Throwable) {
        // Refusing outright is fine too
    }

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/install'));
    Http::assertNotSent(fn (Request $request) => $this->sentTo($this->node, $request) && str_contains($request->url(), '/available'));
});

it('does not stage on a node the admin cannot access', function () {
    $allowed = Node::factory()->create(['name' => 'allowed-node']);
    $this->actingAs($this->nodeRestrictedPluginAdmin($allowed));

    Http::fake([
        '*/api/plugins' => $this->wingsList([], 'full'),
        '*' => $this->wingsStaged('install', 'state-log', 'tok'),
    ]);

    try {
        livewire(ListPlugins::class)
            ->set('activeTab', 'wings')
            ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/state-log']);
    } catch (Throwable) {
        // Refusing outright is fine too
    }

    Http::assertNotSent(fn (Request $request) => $this->sentTo($this->node, $request));
});

it('refuses an invalid plugin name in the install form', function () {
    $this->fakeWingsNode($this->node, [], 'signed');

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '../etc'])
        ->assertHasTableActionErrors(['plugin']);

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('updates in two steps from the plugin row', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('state-log', meta: ['can_update' => true])], 'signed', [
        '*/api/plugins/state-log/update' => Http::sequence()
            ->push($this->wingsStagedBody('update', 'state-log', 'tok-update', ['replaces' => '1.0.0']), 202)
            ->push(['status' => 'updated', 'plugin' => $this->wingsPlugin('state-log')]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertTableActionVisible('exclude_update', "{$this->node->id}:state-log")
        ->callTableAction('exclude_update', "{$this->node->id}:state-log")
        ->assertActionMounted('approveWingsPlugin')
        ->assertMountedActionModalSee('1.0.0')
        ->callMountedAction()
        ->assertNotified(trans('admin/plugin.notifications.updated'));

    $bodies = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/api/plugins/state-log/update'))
        ->map(fn (array $pair) => json_decode($pair[0]->body(), true))
        ->values()
        ->all();

    expect($bodies)->toBe([[], ['approve' => 'tok-update']]);
});

it('says when a plugin is already up to date', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('state-log', meta: ['can_update' => true])], 'signed', [
        '*/api/plugins/state-log/update' => Http::response(['status' => 'up_to_date', 'installed' => '1.2.0', 'latest' => '1.2.0']),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_update', "{$this->node->id}:state-log")
        ->assertActionNotMounted('approveWingsPlugin')
        ->assertNotified(trans('admin/plugin.wings.up_to_date', ['name' => 'state-log', 'installed' => '1.2.0', 'latest' => '1.2.0']));
});

it('hides update when wings says the plugin cannot be updated', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('state-log', meta: ['can_update' => false])], 'signed');

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertTableActionHidden('exclude_update', "{$this->node->id}:state-log");
});

it('imports from a url in two steps on a full node', function () {
    $this->fakeWingsNode($this->node, [], 'full', [
        '*/api/plugins/import' => Http::sequence()
            ->push($this->wingsStagedBody('import', 'state-log', 'tok-import', ['signed' => false]), 202)
            ->push(['status' => 'installed', 'plugin' => $this->wingsPlugin('state-log')]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_import', data: [
            'node' => $this->node->id,
            'source' => 'url',
            'url' => 'https://example.test/state-log.tar.gz',
            'sha256' => str_repeat('A', 64),
        ])
        ->assertHasNoTableActionErrors()
        ->assertActionMounted('approveWingsPlugin')
        ->assertMountedActionModalSee(trans('admin/plugin.wings.signed_no'))
        ->callMountedAction()
        ->assertNotified(trans('admin/plugin.notifications.installed'));

    $bodies = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/api/plugins/import'))
        ->map(fn (array $pair) => json_decode($pair[0]->body(), true))
        ->values()
        ->all();

    expect($bodies)->toBe([
        ['url' => 'https://example.test/state-log.tar.gz', 'sha256' => str_repeat('a', 64)],
        ['approve' => 'tok-import'],
    ]);
});

it('refuses an import url that is not https', function () {
    $this->fakeWingsNode($this->node, [], 'full');

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_import', data: ['node' => $this->node->id, 'source' => 'url', 'url' => 'http://example.test/x.tar.gz'])
        ->assertHasTableActionErrors(['url']);

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('imports an uploaded archive in two steps on a full node', function () {
    $this->fakeWingsNode($this->node, [], 'full', [
        '*/api/plugins/import' => Http::sequence()
            ->push($this->wingsStagedBody('import', 'state-log', 'tok-upload'), 202)
            ->push(['status' => 'installed', 'plugin' => $this->wingsPlugin('state-log')]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_import', data: [
            'node' => $this->node->id,
            'source' => 'file',
            'file' => UploadedFile::fake()->createWithContent('state-log.tar.gz', 'archive bytes'),
            'signature' => 'c2lnbmF0dXJl',
        ])
        ->assertHasNoTableActionErrors()
        ->assertActionMounted('approveWingsPlugin')
        ->callMountedAction();

    $imports = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/api/plugins/import'))->values();

    // A multipart upload, not the daemon client's default JSON
    expect($imports)->toHaveCount(2)
        ->and($imports[0][0]->header('Content-Type')[0])->toStartWith('multipart/form-data; boundary=')
        ->and($imports[0][0]->body())->toContain('name="plugin"; filename="state-log.tar.gz"')
        ->and($imports[0][0]->body())->toContain('archive bytes')
        ->and($imports[0][0]->body())->toContain('name="signature"')
        ->and(json_decode($imports[1][0]->body(), true))->toBe(['approve' => 'tok-upload']);
});

it('says which conflict the node reported at approval', function (array $error, string $key) {
    $this->fakeWingsNode($this->node, [], 'signed', [
        '*/api/plugins/state-log/install' => Http::sequence()
            ->push($this->wingsStagedBody('install', 'state-log', 'tok-old'), 202)
            ->push($error, 409, $this->wingsHeaders($this->node)),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/state-log'])
        ->callMountedAction();

    expect($this->lastNotification()->getBody())->toContain(trans("admin/plugin.wings.$key", ['message' => $error['error']]));
})->with([
    // Contract v3.1: 409s at approval carry a reason
    'used or expired token' => [['error' => 'unknown, used or expired approval token', 'reason' => 'token'], 'approval_token'],
    'installed plugin changed' => [['error' => 'the plugin changed since staging', 'reason' => 'destination_changed'], 'approval_destination'],
    // An older Wings sends no reason
    'no reason' => [['error' => 'conflict'], 'approval_conflict'],
]);
