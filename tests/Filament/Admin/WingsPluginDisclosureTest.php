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

// What the admin is told about a plugin's code and the user it runs as (contract v3.2, S, A3 and A4).
// A Wings older than v3.2 sends none of it, which shows as "unknown" and changes nothing else.

uses(FakesWingsPlugins::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);

    cache()->flush();
    WingsPlugin::flush();

    $this->node = Node::factory()->create(['name' => 'plugin-node']);

    // Stages an install whose 202 is $staged, and returns the mounted approval
    $this->stage = function (array $staged) {
        $this->fakeWingsNode($this->node, [], 'signed', [
            '*/api/plugins/state-log/install' => Http::sequence()
                ->push($staged, 202)
                ->push(['status' => 'installed', 'plugin' => $this->wingsPlugin('state-log')]),
        ]);

        return livewire(ListPlugins::class)
            ->set('activeTab', 'wings')
            ->callTableAction('wings_install', data: ['node' => $this->node->id, 'plugin' => '@pelican/state-log'])
            ->assertHasNoTableActionErrors()
            ->assertActionMounted('approveWingsPlugin');
    };
});

afterEach(function () {
    Filament::setCurrentPanel(null);
    cache()->flush();
    WingsPlugin::flush();
});

/** The sentence contract S requires at every approval, for $user. */
function wingsRunsAsSentence(string $user): string
{
    return "This plugin runs on this node as $user, with that user's access to the machine. The host access listed only limits what it can ask Wings.";
}

it('shows a binary plugin, its binary files, the user it runs as and the contract sentence before approval', function () {
    $component = ($this->stage)($this->wingsStagedBody('install', 'state-log', 'tok-bin', [
        'signed' => false,
        'form' => 'binary',
        'binary_files' => ['bin/statecount', 'lib/libstate.so'],
        'runs_as' => 'pelican',
    ]))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.form'))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.form_binary'))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.form_binary_help'))
        ->assertMountedActionModalSee('bin/statecount')
        ->assertMountedActionModalSee('lib/libstate.so')
        ->assertMountedActionModalSee(trans('admin/plugin.wings.runs_as'))
        ->assertMountedActionModalSee(wingsRunsAsSentence('pelican'))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.not_guaranteed'))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.signed_no'))
        ->assertMountedActionModalSee('server.logs');

    // Shown before anything is approved
    Http::assertNotSent(fn (Request $request) => array_key_exists('approve', json_decode($request->body(), true) ?: []));

    $component->callMountedAction()->assertNotified(trans('admin/plugin.notifications.installed'));
});

it('shows a source plugin as source, with no binary files', function () {
    ($this->stage)($this->wingsStagedBody('install', 'state-log', 'tok-src', ['runs_as' => 'wings-plugins']))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.form_source'))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.form_source_help'))
        ->assertMountedActionModalDontSee(trans('admin/plugin.wings.form_binary_help'))
        ->assertMountedActionModalDontSee(trans('admin/plugin.wings.binary_files'))
        ->assertMountedActionModalSee(wingsRunsAsSentence('wings-plugins'))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.not_guaranteed'));
});

it('lists at most 20 binary files and says how many more there are', function () {
    $files = array_map(fn (int $i) => sprintf('bin/tool-%02d', $i), range(1, 23));

    ($this->stage)($this->wingsStagedBody('install', 'state-log', 'tok-many', ['form' => 'binary', 'binary_files' => $files]))
        ->assertMountedActionModalSee('bin/tool-20')
        ->assertMountedActionModalDontSee('bin/tool-21')
        ->assertMountedActionModalSee(trans('admin/plugin.wings.binary_files_more', ['count' => 3]));
});

it('shows unknown, and still approves, when an older wings does not say the form or user', function () {
    $staged = $this->wingsStagedBody('install', 'state-log', 'tok-old');
    unset($staged['form'], $staged['binary_files'], $staged['runs_as']);

    $component = ($this->stage)($staged)
        ->assertMountedActionModalSee(trans('admin/plugin.wings.form_unknown'))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.form_unknown_help'))
        ->assertMountedActionModalSee(wingsRunsAsSentence(trans('admin/plugin.wings.runs_as_unknown_user')))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.not_guaranteed'))
        ->assertMountedActionModalDontSee(trans('admin/plugin.wings.form_source_help'));

    $component->callMountedAction()->assertNotified(trans('admin/plugin.notifications.installed'));

    Http::assertSent(fn (Request $request) => (json_decode($request->body(), true) ?: []) === ['approve' => 'tok-old']);
});

it('shows unknown for a form, user or file list it does not understand', function () {
    ($this->stage)($this->wingsStagedBody('install', 'state-log', 'tok-odd', [
        'form' => 'native',
        'binary_files' => 'bin/statecount',
        'runs_as' => ['root'],
    ]))
        ->assertMountedActionModalSee(trans('admin/plugin.wings.form_unknown'))
        ->assertMountedActionModalSee(wingsRunsAsSentence(trans('admin/plugin.wings.runs_as_unknown_user')))
        ->assertMountedActionModalDontSee('bin/statecount');
});

it('reads the form, binary files and user of each plugin row', function () {
    $this->fakeWingsNode($this->node, [
        $this->wingsPlugin('state-log'),
        $this->wingsPlugin('statecount', meta: ['form' => 'binary', 'binary_files' => ['wings-plugin-statecount'], 'runs_as' => 'pelican']),
        $this->wingsPlugin('builtin', ['kind' => 'native'], ['form' => 'native', 'runs_as' => 'root']),
    ]);

    $rows = WingsPlugin::scoped()->where('node_id', $this->node->id)->get()->keyBy('plugin_id');

    expect($rows['state-log']->form)->toBe('source')
        ->and($rows['state-log']->binaryFileList())->toBe([])
        ->and($rows['state-log']->runs_as)->toBe('pelican')
        ->and($rows['statecount']->form)->toBe('binary')
        ->and($rows['statecount']->binaryFileList())->toBe(['wings-plugin-statecount'])
        ->and($rows['builtin']->form)->toBe('native')
        ->and($rows['builtin']->runs_as)->toBe('root');

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertCanSeeTableRecords($rows->values())
        ->assertSee(trans('admin/plugin.wings.form'))
        ->assertSee(trans('admin/plugin.wings.runs_as'))
        ->assertTableColumnFormattedStateSet('form', trans('admin/plugin.wings.form_source'), $rows['state-log'])
        ->assertTableColumnFormattedStateSet('form', trans('admin/plugin.wings.form_binary'), $rows['statecount'])
        ->assertTableColumnFormattedStateSet('form', trans('admin/plugin.wings.form_native'), $rows['builtin'])
        ->assertTableColumnStateSet('runs_as', 'pelican', $rows['statecount'])
        ->assertTableColumnStateSet('runs_as', 'root', $rows['builtin']);
});

it('shows unknown for the form and user of a plugin listed by an older wings', function () {
    $old = $this->wingsPlugin('state-log');
    unset($old['meta']['form'], $old['meta']['binary_files'], $old['meta']['runs_as']);

    $odd = $this->wingsPlugin('odd', meta: ['form' => 'compiled', 'binary_files' => null, 'runs_as' => 0]);

    $this->fakeWingsNode($this->node, [$old, $odd]);

    $rows = WingsPlugin::scoped()->where('node_id', $this->node->id)->get()->keyBy('plugin_id');

    expect($rows)->toHaveCount(2)
        ->and($rows['state-log']->isPlugin())->toBeTrue()
        ->and($rows['state-log']->form)->toBeNull()
        ->and($rows['state-log']->runs_as)->toBeNull()
        ->and($rows['state-log']->binaryFileList())->toBe([])
        ->and($rows['odd']->isPlugin())->toBeTrue()
        ->and($rows['odd']->form)->toBeNull()
        ->and($rows['odd']->runs_as)->toBeNull();

    livewire(ListPlugins::class)
        ->set('activeTab', 'wings')
        ->assertCanSeeTableRecords($rows->values())
        ->assertTableColumnFormattedStateSet('form', trans('admin/plugin.wings.form_unknown'), $rows['state-log'])
        ->assertTableColumnStateSet('runs_as', trans('admin/plugin.wings.form_unknown'), $rows['state-log'])
        ->assertTableColumnFormattedStateSet('form', trans('admin/plugin.wings.form_unknown'), $rows['odd']);
});
