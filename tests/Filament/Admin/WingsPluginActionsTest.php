<?php

use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Models\Node;
use App\Models\Role;
use App\Models\WingsPlugin;
use App\Repositories\Daemon\DaemonPluginRepository;
use App\Services\Nodes\WingsPluginFailure;
use App\Tests\Traits\FakesWingsPlugins;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

// Row actions on Wings plugins and their settings (contract v3, A2, A3, A3.1 and A7).

uses(FakesWingsPlugins::class);

/** @return array<int, array<string, mixed>> */
function wingsActionsSettingsSchema(): array
{
    return [
        ['key' => 'webhook_url', 'label' => 'Webhook URL', 'type' => 'string', 'required' => true, 'secret' => true],
        ['key' => 'retries', 'type' => 'number', 'default' => 3, 'required' => false, 'secret' => false],
        ['key' => 'ratio', 'type' => 'number', 'required' => false, 'secret' => false],
        ['key' => 'verbose', 'type' => 'boolean', 'default' => false, 'required' => false, 'secret' => false],
        ['key' => 'level', 'type' => 'select', 'options' => [['value' => 'info', 'label' => 'Info'], ['value' => 'warn', 'label' => 'Warn']], 'default' => 'info', 'required' => false, 'secret' => false],
        ['key' => 'port', 'type' => 'select', 'options' => [['value' => 80, 'label' => 'HTTP'], ['value' => 443, 'label' => 'HTTPS']], 'required' => false, 'secret' => false],
        // Not a v2 type
        ['key' => 'notes', 'type' => 'text', 'required' => false, 'secret' => false],
    ];
}

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

it('shows only the actions the can flags allow', function () {
    $this->fakeWingsNode($this->node, [
        $this->wingsPlugin('on', meta: ['can_disable' => true, 'can_uninstall' => true]),
        $this->wingsPlugin('off', meta: ['status' => 'disabled', 'enabled' => false, 'can_enable' => true, 'can_disable' => false]),
        $this->wingsPlugin('native', ['kind' => 'native'], ['trust' => 'native', 'can_disable' => false, 'can_uninstall' => false]),
    ]);

    $id = $this->node->id;

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertTableActionVisible('exclude_disable', "$id:on")
        ->assertTableActionHidden('exclude_enable', "$id:on")
        ->assertTableActionVisible('exclude_uninstall', "$id:on")
        ->assertTableActionVisible('exclude_delete', "$id:on")
        ->assertTableActionVisible('exclude_enable', "$id:off")
        ->assertTableActionHidden('exclude_disable', "$id:off")
        ->assertTableActionHidden('exclude_uninstall', "$id:off")
        ->assertTableActionHidden('exclude_delete', "$id:off")
        ->assertTableActionHidden('exclude_settings', "$id:off")
        ->assertTableActionHidden('exclude_install', "$id:off")
        ->assertTableActionHidden('exclude_disable', "$id:native")
        ->assertTableActionHidden('exclude_uninstall', "$id:native");
});

it('hides every action on rows that are not plugins', function (string $kind) {
    $answer = match ($kind) {
        'forbidden' => $this->wingsForbidden($this->node, 'off', 'view'),
        'no api' => $this->wingsNotFound($this->node),
        'down' => Http::response('bad gateway', 502, $this->wingsHeaders($this->node)),
    };

    Http::fake([
        "*{$this->node->fqdn}*/api/plugins" => $answer,
        '*/api/plugins' => $this->wingsList([]),
    ]);

    $key = WingsPlugin::scoped()->where('node_id', $this->node->id)->first()->getKey();

    $component = livewire(ListPlugins::class)->set('activeTab', 'wings');

    foreach (['exclude_settings', 'exclude_enable', 'exclude_disable', 'exclude_update', 'exclude_delete', 'exclude_uninstall', 'exclude_install'] as $action) {
        $component->assertTableActionHidden($action, $key);
    }
})->with(['forbidden', 'no api', 'down']);

it('uninstalls without deleting, and deletes with delete=true', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('state-log', meta: ['can_uninstall' => true])], more: [
        '*/api/plugins/state-log/uninstall*' => Http::response(['status' => 'uninstalled', 'deleted' => false]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_uninstall', "{$this->node->id}:state-log");

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/api/plugins/state-log/uninstall?delete=false'));

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_delete', "{$this->node->id}:state-log");

    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/api/plugins/state-log/uninstall?delete=true'));
});

it('explains a 403 from an action with the level and how to raise it', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('state-log', meta: ['can_uninstall' => true])], more: [
        '*/api/plugins/state-log/uninstall*' => $this->wingsForbidden($this->node, 'view', 'signed'),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_uninstall', "{$this->node->id}:state-log")
        ->assertNotified(trans('admin/plugin.notifications.uninstall_error'));

    expect(trans('admin/plugin.wings.forbidden', ['level' => 'view', 'required' => 'signed']))
        ->toContain('wings plugins panel-access signed');
});

it('reads a 404 on a plugin path as no such plugin, not as a wings without the api', function () {
    $this->fakeWingsNode($this->node, [], more: [
        '*/api/plugins/gone/enable' => $this->wingsError($this->node, 404, 'plugin "gone" not found'),
    ]);

    try {
        (new DaemonPluginRepository())->setNode($this->node)->enable('gone');
        $this->fail('Wings answered 404');
    } catch (RequestException $exception) {
        expect(WingsPluginFailure::message($exception))->toBe(trans('admin/plugin.wings.not_found'))
            ->and(WingsPluginFailure::message($exception))->not->toContain('API');
    }
});

it('lets an admin configure a disabled plugin', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('notify', ['settings' => wingsActionsSettingsSchema()], [
        'status' => 'disabled', 'enabled' => false, 'can_disable' => false, 'can_enable' => true, 'can_configure' => true,
    ])]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertTableActionVisible('exclude_settings', "{$this->node->id}:notify");
});

it('hides settings when wings says the plugin cannot be configured', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('notify', ['settings' => wingsActionsSettingsSchema()], ['can_configure' => false])], 'view');

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertTableActionHidden('exclude_settings', "{$this->node->id}:notify");
});

it('builds the settings form from the four contract types', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('notify', ['settings' => wingsActionsSettingsSchema()], [
        'can_configure' => true, 'values' => ['webhook_url' => '********'],
    ])]);

    $fields = collect(WingsPlugin::scoped()->where('plugin_id', 'notify')->first()->getSettingsForm())
        ->keyBy(fn ($field) => $field->getName());

    expect($fields['webhook_url'])->toBeInstanceOf(TextInput::class)
        ->and($fields['webhook_url']->isPassword())->toBeTrue()
        ->and($fields['retries'])->toBeInstanceOf(TextInput::class)
        ->and($fields['retries']->isNumeric())->toBeTrue()
        ->and($fields['verbose'])->toBeInstanceOf(Toggle::class)
        ->and($fields['level'])->toBeInstanceOf(Select::class)
        ->and($fields['-readonly-6']->isDisabled())->toBeTrue()
        ->and($fields['-readonly-6']->isDehydrated())->toBeFalse();
});

it('shows a setting with a key wings does not accept as read-only and sends its stored value back', function () {
    $schema = [
        ['key' => 'logging.level', 'type' => 'string', 'required' => false, 'secret' => false],
        ['key' => 'has space', 'type' => 'number', 'required' => false, 'secret' => false],
        ['key' => 'retries', 'type' => 'number', 'default' => 3, 'required' => false, 'secret' => false],
    ];

    $this->fakeWingsNode($this->node, [$this->wingsPlugin('notify', ['settings' => $schema], [
        'can_configure' => true, 'values' => ['logging.level' => 'debug', 'has space' => 7],
    ])], more: [
        '*/api/plugins/notify/settings' => Http::response(['status' => 'saved', 'values' => []]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_settings', "{$this->node->id}:notify", ['retries' => '4'])
        ->assertHasNoTableActionErrors();

    // The stored values of the read-only settings go back as they were; Wings would unset them otherwise
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && json_decode($request->body(), true) === [
            'logging.level' => 'debug',
            'has space' => 7,
            'retries' => 4,
        ]);

    $plugin = WingsPlugin::scoped()->where('plugin_id', 'notify')->first();
    $fields = collect($plugin->getSettingsForm())->keyBy(fn ($field) => $field->getName());

    // No field is named after the dotted key, so the form can't read it as nesting
    expect($fields->keys()->all())->toBe(['-readonly-0', '-readonly-1', 'retries'])
        ->and($fields['-readonly-0']->isDisabled())->toBeTrue()
        ->and($fields['-readonly-1']->isDisabled())->toBeTrue()
        ->and($plugin->getSettingsFormData())->toBe(['retries' => 3]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->mountTableAction('exclude_settings', "{$this->node->id}:notify")
        ->assertTableActionDataSet(['-readonly-0' => 'debug', '-readonly-1' => '7']);
});

it('reports 422 messages for keys that are not fields in the notification', function () {
    $schema = [
        ['key' => 'logging.level', 'type' => 'string', 'required' => false, 'secret' => false],
        ['key' => 'retries', 'type' => 'number', 'required' => false, 'secret' => false],
    ];

    $this->fakeWingsNode($this->node, [$this->wingsPlugin('notify', ['settings' => $schema], [
        'can_configure' => true, 'values' => ['logging.level' => 'loud'],
    ])], more: [
        '*/api/plugins/notify/settings' => $this->wingsError($this->node, 422, 'plugin: invalid settings', [
            'fields' => ['logging.level' => 'unknown setting'],
        ]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_settings', "{$this->node->id}:notify", ['retries' => '2'])
        ->assertHasNoTableActionErrors()
        ->assertNotified(trans('admin/plugin.notifications.settings_error'));
});

it('sends numbers as numbers, keeps an unchanged secret and leaves untouched defaults unset', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('notify', ['settings' => wingsActionsSettingsSchema()], [
        'can_configure' => true, 'values' => ['webhook_url' => '********', 'notes' => 'kept as it was'],
    ])], more: [
        '*/api/plugins/notify/settings' => Http::response(['status' => 'saved', 'values' => []]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_settings', "{$this->node->id}:notify", [
            'webhook_url' => '********',
            'retries' => '3',
            'ratio' => '0.5',
            'verbose' => false,
            'level' => 'info',
            'port' => '443',
        ])
        ->assertHasNoTableActionErrors();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/api/plugins/notify/settings')
        && json_decode($request->body(), true) === [
            'webhook_url' => '********',
            'ratio' => 0.5,
            'port' => 443,
            'notes' => 'kept as it was',
        ]);
});

it('sends a changed default and a new secret', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('notify', ['settings' => wingsActionsSettingsSchema()], [
        'can_configure' => true, 'values' => ['webhook_url' => '********', 'retries' => 3],
    ])], more: [
        '*/api/plugins/notify/settings' => Http::response(['status' => 'saved', 'values' => []]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_settings', "{$this->node->id}:notify", [
            'webhook_url' => 'https://hooks.example.test/new',
            'retries' => '5',
            'verbose' => true,
            'level' => 'warn',
            'port' => null,
        ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && json_decode($request->body(), true) === [
            'webhook_url' => 'https://hooks.example.test/new',
            'retries' => 5,
            'verbose' => true,
            'level' => 'warn',
        ]);
});

it('shows the 422 field messages on the settings form', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('notify', ['settings' => wingsActionsSettingsSchema()], [
        'can_configure' => true, 'values' => ['webhook_url' => '********'],
    ])], more: [
        '*/api/plugins/notify/settings' => $this->wingsError($this->node, 422, 'plugin: invalid settings', [
            'fields' => ['retries' => 'setting "retries" must be a number'],
        ]),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_settings', "{$this->node->id}:notify", ['webhook_url' => '********', 'retries' => '7'])
        ->assertHasTableActionErrors(['retries']);
});

it('reports a settings failure without fields as a notification', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('notify', ['settings' => wingsActionsSettingsSchema()], [
        'can_configure' => true, 'values' => ['webhook_url' => '********'],
    ])], more: [
        '*/api/plugins/notify/settings' => $this->wingsError($this->node, 409, 'plugin is not trusted'),
    ]);

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_settings', "{$this->node->id}:notify", ['webhook_url' => '********'])
        ->assertNotified(trans('admin/plugin.notifications.settings_error'));
});

it('keeps numbers past the integer range as the number entered', function () {
    $this->fakeWingsNode($this->node, [$this->wingsPlugin('notify', ['settings' => wingsActionsSettingsSchema()], [
        'can_configure' => true, 'values' => ['webhook_url' => '********'],
    ])], more: [
        '*/api/plugins/notify/settings' => Http::response(['status' => 'saved', 'values' => []]),
    ]);

    // 2^63: one past PHP_INT_MAX, which compares equal to it once both are floats
    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->callTableAction('exclude_settings', "{$this->node->id}:notify", [
            'webhook_url' => '********',
            'ratio' => 9223372036854775808.0,
            'retries' => '9223372036854775808',
        ])
        ->assertHasNoTableActionErrors();

    Http::assertSent(function (Request $request) {
        if ($request->method() !== 'PUT') {
            return false;
        }
        $body = json_decode($request->body(), true);

        return is_float($body['ratio'] ?? null) && $body['ratio'] === 9223372036854775808.0
            && is_float($body['retries'] ?? null) && $body['retries'] === 9223372036854775808.0;
    });
});
