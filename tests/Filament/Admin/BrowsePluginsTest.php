<?php

use App\Filament\Admin\Resources\Plugins\Pages\BrowsePlugins;
use App\Jobs\Plugin\InstallPlugin;
use App\Models\Plugin;
use App\Models\Role;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

const HUB = 'https://hub.example.test';

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    config()->set('panel.plugin.hub_url', HUB);
    config()->set('panel.plugin.hub_api_key');
});

afterEach(function () {
    Filament::setCurrentPanel(null);

    foreach (['hub-free-plugin', '.hub-free-plugin.bak'] as $dir) {
        File::deleteDirectory(plugin_path($dir));
    }

    foreach (File::glob(plugin_path('.import-*')) as $leftover) {
        File::deleteDirectory($leftover);
    }

    Plugin::refreshRows();
});

function hubPlugin(string $slug, string $pricing = 'free'): array
{
    return [
        'slug' => $slug,
        'title' => 'Hub Free Plugin',
        'author_name' => 'Plugin Author',
        'description' => 'Does free things',
        'pricing_type' => $pricing,
        'downloads_count' => 42,
        'review_percentage' => 97,
        'categories' => [['id' => 1, 'name' => 'Minecraft', 'slug' => 'minecraft']],
        'tags' => [],
        'latest_version' => '1.0.0',
        'last_version_at' => now()->toIso8601String(),
        'repository_url' => 'https://github.com/example/hub-free-plugin',
        'url' => HUB . "/plugins/$slug",
    ];
}

function hubPluginZip(string $id): string
{
    $path = tempnam(sys_get_temp_dir(), 'hub') . '.zip';

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString("$id/plugin.json", json_encode(['id' => $id, 'name' => 'Hub Free Plugin', 'version' => '1.0.0']));
    $zip->close();

    $bytes = file_get_contents($path);
    unlink($path);

    return $bytes;
}

function fakeHub(array $detail, ?string $zip = null, ?string $sha256 = null): void
{
    $slug = $detail['slug'];
    $zip ??= hubPluginZip($slug);

    Http::fake([
        HUB . '/api/plugins/categories' => Http::response(['data' => [['id' => 1, 'name' => 'Minecraft', 'slug' => 'minecraft']]]),
        HUB . '/api/plugins/tags' => Http::response(['data' => []]),
        HUB . "/api/plugins/$slug" => Http::response($detail),
        HUB . '/api/plugins*' => Http::response(['current_page' => 1, 'data' => [hubPlugin($slug)], 'per_page' => 24, 'total' => 1]),
        HUB . "/plugins/$slug/update.json" => Http::response(['*' => [
            'version' => '1.0.0',
            'download_url' => HUB . "/plugins/$slug/download/1/$slug.zip",
            'channel' => 'stable',
            'sha256' => $sha256 ?? hash('sha256', $zip),
        ]]),
        HUB . "/plugins/$slug/download/*" => Http::response($zip),
    ]);
}

function actingAsRootAdmin(): void
{
    [$admin] = generateTestAccount([]);
    test()->actingAs($admin->syncRoles(Role::getRootAdmin()));
}

it('lists free hub plugins for an admin', function () {
    fakeHub(hubPlugin('hub-free-plugin'));
    actingAsRootAdmin();

    livewire(BrowsePlugins::class)
        ->assertSuccessful()
        ->assertSee('Hub Free Plugin')
        ->assertSee('Plugin Author');
});

it('is forbidden without permission to add plugins', function () {
    [$user] = generateTestAccount([]);
    $this->actingAs($user);

    livewire(BrowsePlugins::class)->assertForbidden();
});

it('shows a notice when the hub cannot be reached', function () {
    Http::fake(['*' => Http::response('down', 503)]);
    actingAsRootAdmin();

    livewire(BrowsePlugins::class)
        ->assertSuccessful()
        ->assertSee(trans('admin/plugin.hub.unreachable'));
});

it('installs a free plugin from the hub', function () {
    Queue::fake();
    fakeHub(hubPlugin('hub-free-plugin'));
    actingAsRootAdmin();

    livewire(BrowsePlugins::class)
        ->callAction(TestAction::make('exclude_install')->table('hub-free-plugin'))
        ->assertNotified(trans('admin/plugin.notifications.install_started'));

    expect(File::exists(plugin_path('hub-free-plugin', 'plugin.json')))->toBeTrue();
    Queue::assertPushed(InstallPlugin::class, fn (InstallPlugin $job) => $job->pluginId === 'hub-free-plugin' && !$job->reinstall);
});

it('refuses to install a plugin that is not free', function () {
    Queue::fake();
    fakeHub(hubPlugin('hub-free-plugin', 'paid'));
    actingAsRootAdmin();

    livewire(BrowsePlugins::class)
        ->callAction(TestAction::make('exclude_install')->table('hub-free-plugin'))
        ->assertNotified(trans('admin/plugin.notifications.install_error'));

    expect(File::exists(plugin_path('hub-free-plugin')))->toBeFalse();
    Queue::assertNothingPushed();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/download/'));
});

it('refuses a download that does not match the hub checksum', function () {
    Queue::fake();
    fakeHub(hubPlugin('hub-free-plugin'), sha256: str_repeat('0', 64));
    actingAsRootAdmin();

    livewire(BrowsePlugins::class)
        ->callAction(TestAction::make('exclude_install')->table('hub-free-plugin'))
        ->assertNotified(trans('admin/plugin.notifications.install_error'));

    expect(File::exists(plugin_path('hub-free-plugin')))->toBeFalse();
    Queue::assertNothingPushed();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/download/'));
});

it('filters to installed plugins', function () {
    fakeHub(hubPlugin('hub-free-plugin'));
    actingAsRootAdmin();

    livewire(BrowsePlugins::class)
        ->filterTable('installed')
        ->assertDontSee('Hub Free Plugin');

    File::ensureDirectoryExists(plugin_path('hub-free-plugin'));
    File::put(plugin_path('hub-free-plugin', 'plugin.json'), json_encode(['id' => 'hub-free-plugin', 'name' => 'Hub Free Plugin', 'version' => '1.0.0']));
    Plugin::refreshRows();

    livewire(BrowsePlugins::class)
        ->filterTable('installed')
        ->assertSee('Hub Free Plugin')
        ->assertSee(trans('admin/plugin.hub.installed'));
});

it('hides report issue for plugins that are not downloaded', function () {
    fakeHub(hubPlugin('hub-free-plugin'));
    actingAsRootAdmin();

    livewire(BrowsePlugins::class)
        ->assertActionHidden(TestAction::make('exclude_report_issue')->table('hub-free-plugin'));
});

it('opens a prefilled github issue when reporting a plugin', function () {
    fakeHub(hubPlugin('hub-free-plugin'));
    actingAsRootAdmin();

    File::ensureDirectoryExists(plugin_path('hub-free-plugin'));
    File::put(plugin_path('hub-free-plugin', 'plugin.json'), json_encode(['id' => 'hub-free-plugin', 'name' => 'Hub Free Plugin', 'version' => '1.0.0']));
    Plugin::refreshRows();

    livewire(BrowsePlugins::class)
        ->callAction(TestAction::make('exclude_report_issue')->table('hub-free-plugin'), [
            'title' => 'Broken button',
            'description' => 'It does nothing',
        ])
        ->assertHasNoFormErrors()
        ->assertNotNotified(trans('admin/plugin.hub.report_issue_unavailable'))
        ->tap(fn ($test) => expect($test->effects['xjs'][0]['expression'] ?? '')
            ->toStartWith("window.open('https:\\/\\/github.com\\/example\\/hub-free-plugin\\/issues\\/new?title=Broken%20button"));
});
