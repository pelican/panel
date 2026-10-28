<?php

use App\Filament\Admin\Resources\Plugins\Pages\BrowsePlugins;
use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
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

it('links report issue to the plugin hub page', function () {
    $plugin = hubPlugin('hub-free-plugin');
    fakeHub($plugin);
    actingAsRootAdmin();

    File::ensureDirectoryExists(plugin_path('hub-free-plugin'));
    File::put(plugin_path('hub-free-plugin', 'plugin.json'), json_encode(['id' => 'hub-free-plugin', 'name' => 'Hub Free Plugin', 'version' => '1.0.0']));
    Plugin::refreshRows();

    livewire(BrowsePlugins::class)
        ->assertActionVisible(TestAction::make('exclude_report_issue')->table('hub-free-plugin'))
        ->assertActionHasUrl(TestAction::make('exclude_report_issue')->table('hub-free-plugin'), $plugin['url']);
});

/**
 * Fakes a Hub listing of `$count` plugins, ordered high to low by downloads,
 * that honours `page` and `per_page`. Rating sorts list them the other way
 * round, and any other sort lists only "Unsorted Plugin".
 */
function fakeHubListing(int $count): void
{
    Http::fake([
        HUB . '/api/plugins/categories' => Http::response(['data' => []]),
        HUB . '/api/plugins/tags' => Http::response(['data' => []]),
        HUB . '/api/plugins*' => function ($request) use ($count) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);
            $perPage = (int) ($query['per_page'] ?? 24);
            $sort = $query['sort'] ?? null;

            $data = match ($sort) {
                'popular' => collect(range(0, $count - 1)),
                'rating' => collect(range($count - 1, 0)),
                default => collect([-1]),
            };

            $data = $data
                ->map(fn (int $i) => [...hubPlugin("hub-plugin-$i"), 'title' => $i < 0 ? 'Unsorted Plugin' : sprintf('Listed Plugin %02d', $i)])
                ->forPage($page, $perPage)
                ->values();

            return Http::response(['current_page' => $page, 'data' => $data, 'per_page' => $perPage, 'total' => $sort === 'popular' || $sort === 'rating' ? $count : 1]);
        },
    ]);
}

it('sorts by downloads from the hub when the column is sorted', function () {
    fakeHubListing(3);
    actingAsRootAdmin();

    livewire(BrowsePlugins::class)
        ->filterTable('sort', 'name')
        ->assertSee('Unsorted Plugin')
        ->sortTable('downloads_count', 'desc')
        ->assertDontSee('Unsorted Plugin')
        ->assertSeeInOrder(['Listed Plugin 00', 'Listed Plugin 01', 'Listed Plugin 02']);
});

it('sorts by rating from the hub when the column is sorted', function () {
    fakeHubListing(3);
    actingAsRootAdmin();

    livewire(BrowsePlugins::class)
        ->filterTable('sort', 'name')
        ->sortTable('review_percentage', 'desc')
        ->assertDontSee('Unsorted Plugin')
        ->assertSeeInOrder(['Listed Plugin 02', 'Listed Plugin 01', 'Listed Plugin 00']);
});

it('reverses the hub order across pages when sorting ascending', function () {
    fakeHubListing(15);
    actingAsRootAdmin();

    livewire(BrowsePlugins::class)
        ->set('tableRecordsPerPage', 12)
        ->sortTable('downloads_count', 'asc')
        ->assertSeeInOrder(['Listed Plugin 14', 'Listed Plugin 13', 'Listed Plugin 03'])
        ->assertDontSee('Listed Plugin 02')
        ->call('gotoPage', 2)
        ->assertSeeInOrder(['Listed Plugin 02', 'Listed Plugin 01', 'Listed Plugin 00'])
        ->assertDontSee('Listed Plugin 03');
});

it('opens the hub page when reporting an installed plugin', function () {
    fakeHub(hubPlugin('hub-free-plugin'));
    actingAsRootAdmin();

    File::ensureDirectoryExists(plugin_path('hub-free-plugin'));
    File::put(plugin_path('hub-free-plugin', 'plugin.json'), json_encode(['id' => 'hub-free-plugin', 'name' => 'Hub Free Plugin', 'version' => '1.0.0']));
    Plugin::refreshRows();

    livewire(ListPlugins::class)
        ->callAction(TestAction::make('exclude_report_issue')->table(Plugin::find('hub-free-plugin')))
        ->assertNotNotified(trans('admin/plugin.hub.report_issue_unavailable'))
        ->tap(fn ($test) => expect($test->effects['xjs'][0]['expression'] ?? '')
            ->toBe("window.open('https:\/\/hub.example.test\/plugins\/hub-free-plugin', '_blank', 'noopener')"));
});
