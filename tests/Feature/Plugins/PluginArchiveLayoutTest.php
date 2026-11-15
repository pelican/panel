<?php

use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\User;
use App\Services\Helpers\PluginRegistryConfig;
use App\Services\Helpers\PluginRegistryService;
use App\Services\Helpers\PluginService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\TemporaryDirectory\TemporaryDirectory;

use function Pest\Livewire\livewire;

// pelican/wings plugin contract v3.2, R1 "Archive layout", for Panel plugins: plugin.json at the archive's root,
// or inside a single top-level folder with nothing else beside it. Anything else is refused with a message about
// the layout, and no error shows an internal staging path. All HTTP is faked.

const LAYOUT_REGISTRY_URL = 'https://layout-registry.example.test/registry.json';
const LAYOUT_DOWNLOADS = 'https://layout-registry.example.test/plugins/';

beforeEach(function () {
    config()->set('panel.plugin.registries', []);
    config()->set('panel.plugin.trusted_keys');
    config()->set('panel.plugin.require_signatures', false);

    $this->tempDirs = [];
});

afterEach(function () {
    Filament::setCurrentPanel(null);

    foreach ([...File::glob(plugin_path('lay-*')), ...File::glob(plugin_path('.lay-*')), ...File::glob(plugin_path('.import-*'))] as $dir) {
        File::deleteDirectory($dir);
    }

    foreach ($this->tempDirs as $dir) {
        $dir->delete();
    }

    Plugin::refreshRows();
});

/** A plugin.json for $id. */
function layoutManifest(string $id): string
{
    return json_encode(['id' => $id, 'name' => 'Layout Test', 'author' => 'Example', 'version' => '1.0.0', 'category' => 'plugin']);
}

/**
 * The bytes of a zip of [path => contents].
 *
 * @param  array<string, string>  $entries
 */
function layoutZip(array $entries): string
{
    $dir = TemporaryDirectory::make();
    $path = $dir->path('layout.zip');

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);
    foreach ($entries as $name => $contents) {
        str_ends_with($name, '/') ? $zip->addEmptyDir(rtrim($name, '/')) : $zip->addFromString($name, $contents);
    }
    $zip->close();

    $bytes = file_get_contents($path);
    $dir->delete();

    return $bytes;
}

/** Fails if $message shows where the Panel stages or keeps archives. */
function expectNoStagingPath(string $message): void
{
    expect($message)
        ->not->toContain(base_path('plugins'))
        ->not->toContain('.import-')
        ->not->toContain(rtrim(sys_get_temp_dir(), '/') . '/');
}

/**
 * Configures one registry, "layout", that lists each [id => archive].
 *
 * @param  array<string, string>  $archives
 */
function layoutRegistry(array $archives): void
{
    config()->set('panel.plugin.registries', PluginRegistryConfig::parse(json_encode(['layout' => ['url' => LAYOUT_REGISTRY_URL]]), fn () => null));

    $entries = [];
    $downloads = [];
    foreach ($archives as $id => $archive) {
        $entries[] = ['name' => $id, 'version' => '1.0.0', 'target' => 'panel', 'download' => LAYOUT_DOWNLOADS . "$id.zip", 'sha256' => hash('sha256', $archive)];
        $downloads[LAYOUT_DOWNLOADS . "$id.zip"] = Http::response($archive);
    }

    Http::fake([LAYOUT_REGISTRY_URL => Http::response(['plugins' => $entries]), ...$downloads]);
}

/** The last notification sent, read the way Filament's assertNotified() reads them. */
function layoutLastNotification(): Notification
{
    app(NotificationMailbox::class)->flush();

    $notifications = new Notifications();
    $notifications->mount();
    $notifications->pullNotifications();

    return $notifications->notifications->last() ?? throw new RuntimeException('No notification was sent.');
}

/**
 * Layouts R1 accepts, each with plugin.json for "lay-ok".
 *
 * @return array<string, array{array<string, string>}>
 */
function acceptedLayouts(): array
{
    return [
        'plugin.json at the root, with the rest of the plugin beside it' => [[
            'plugin.json' => layoutManifest('lay-ok'),
            'src/LayoutPlugin.php' => '<?php',
            'README.md' => 'readme',
        ]],
        'a single top-level folder, named anything' => [[
            'layout-plugin-1.0.0/plugin.json' => layoutManifest('lay-ok'),
            'layout-plugin-1.0.0/src/LayoutPlugin.php' => '<?php',
        ]],
        'a single top-level folder with its own directory entry' => [[
            'lay-ok/' => '',
            'lay-ok/plugin.json' => layoutManifest('lay-ok'),
        ]],
    ];
}

/**
 * Layouts R1 refuses.
 *
 * @return array<string, array{array<string, string>}>
 */
function refusedLayouts(): array
{
    return [
        'a folder with plugin.json and a file beside it' => [[
            'lay-bad/plugin.json' => layoutManifest('lay-bad'),
            'README.md' => 'readme',
        ]],
        'a folder with plugin.json and another folder beside it' => [[
            'lay-bad/plugin.json' => layoutManifest('lay-bad'),
            'docs/index.md' => 'docs',
        ]],
        'two folders that each have a plugin.json' => [[
            'lay-bad/plugin.json' => layoutManifest('lay-bad'),
            'lay-bad-copy/plugin.json' => layoutManifest('lay-bad'),
        ]],
        'plugin.json two folders deep' => [[
            'outer/lay-bad/plugin.json' => layoutManifest('lay-bad'),
        ]],
        'no plugin.json at all' => [[
            'readme.txt' => 'nothing to see here',
        ]],
        'a plugin.json that is a folder' => [[
            'plugin.json/inner.txt' => 'not a manifest',
        ]],
        'a single folder whose plugin.json is a folder' => [[
            'lay-bad/plugin.json/inner.txt' => 'not a manifest',
        ]],
    ];
}

it('accepts plugin.json at the root or in a single top-level folder', function (array $entries) {
    $dir = TemporaryDirectory::make();
    $this->tempDirs[] = $dir;
    file_put_contents($path = $dir->path('upload.zip'), layoutZip($entries));

    $id = app(PluginService::class)->downloadPluginFromFile(new UploadedFile($path, 'upload.zip', 'application/zip', null, true));

    expect($id)->toBe('lay-ok')
        ->and(plugin_path('lay-ok', 'plugin.json'))->toBeFile()
        ->and(File::glob(plugin_path('.import-*')))->toBe([]);
})->with(acceptedLayouts());

it('refuses any other layout with a message about the layout, and installs nothing', function (array $entries) {
    $dir = TemporaryDirectory::make();
    $this->tempDirs[] = $dir;
    file_put_contents($path = $dir->path('upload.zip'), layoutZip($entries));

    try {
        app(PluginService::class)->downloadPluginFromFile(new UploadedFile($path, 'upload.zip', 'application/zip', null, true));
        $this->fail('Expected the archive to be refused.');
    } catch (Exception $exception) {
        expect($exception->getMessage())->toBe(trans('admin/plugin.notifications.import_layout'));
        expectNoStagingPath($exception->getMessage());
    }

    expect(File::glob(plugin_path('lay-*')))->toBe([])
        ->and(File::glob(plugin_path('.import-*')))->toBe([]);
})->with(refusedLayouts());

it('applies the layout rule to url imports and registry installs too', function () {
    $refused = layoutZip(['lay-url/plugin.json' => layoutManifest('lay-url'), 'README.md' => 'readme']);

    Http::fake(['https://downloads.example.test/*' => Http::response($refused)]);
    expect(fn () => app(PluginService::class)->downloadPluginFromUrl('https://downloads.example.test/lay-url.zip'))
        ->toThrow(Exception::class, trans('admin/plugin.notifications.import_layout'));

    layoutRegistry([
        'lay-url' => $refused,
        // A GitHub-style release archive: one folder named after the release
        'lay-gh' => layoutZip(['lay-gh-1.0.0/plugin.json' => layoutManifest('lay-gh'), 'lay-gh-1.0.0/src/Plugin.php' => '<?php']),
    ]);

    expect(fn () => app(PluginService::class)->downloadPluginFromRegistry(app(PluginRegistryService::class)->resolve('@layout/lay-url')))
        ->toThrow(Exception::class, trans('admin/plugin.notifications.import_layout'));

    expect(plugin_path('lay-url'))->not->toBeDirectory();

    expect(app(PluginService::class)->downloadPluginFromRegistry(app(PluginRegistryService::class)->resolve('@layout/lay-gh')))->toBe('lay-gh')
        ->and(plugin_path('lay-gh', 'src', 'Plugin.php'))->toBeFile();
});

it('shows the layout refusal, and no staging path, in the install command', function () {
    layoutRegistry(['lay-cli' => layoutZip(['plugin.json/inner.txt' => 'not a manifest'])]);

    $this->artisan('p:plugin:install', ['id' => '@layout/lay-cli', '--force' => true])
        ->expectsOutput('Could not install plugin: ' . trans('admin/plugin.notifications.import_layout'))
        ->doesntExpectOutputToContain(base_path('plugins'))
        ->doesntExpectOutputToContain('.import-')
        ->assertSuccessful();

    expect(plugin_path('lay-cli'))->not->toBeDirectory();
});

it('shows the layout refusal, and no staging path, when an admin installs from a registry', function () {
    Queue::fake();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create()->syncRoles(Role::getRootAdmin()));

    $archive = layoutZip(['plugin.json/inner.txt' => 'not a manifest']);
    layoutRegistry(['lay-ui' => $archive]);

    livewire(ListPlugins::class)
        ->callAction(TestAction::make('install_from_registry')->table(), data: ['reference' => '@layout/lay-ui', 'approved' => [
            'name' => 'lay-ui',
            'version' => '1.0.0',
            'author' => null,
            'description' => null,
            'source' => '@layout (' . LAYOUT_REGISTRY_URL . ')',
            'download' => LAYOUT_DOWNLOADS . 'lay-ui.zip',
            'sha256' => hash('sha256', $archive),
            'signed' => 'no',
            'panel_version' => null,
        ]]);

    $notification = layoutLastNotification();
    $body = (string) $notification->getBody();

    expect($notification->getTitle())->toBe(trans('admin/plugin.notifications.install_error'))
        ->and($body)->toContain(trans('admin/plugin.notifications.import_layout'));
    expectNoStagingPath($body);
    Queue::assertNothingPushed();
});

it('never shows a staging path from a failure inside an import, but keeps the path inside the archive', function () {
    // Stands in for any failure whose message names a file being imported, such as a read error
    File::partialMock()
        ->shouldReceive('json')
        ->andReturnUsing(fn (string $path) => throw new RuntimeException("Could not read $path"));

    $dir = TemporaryDirectory::make();
    $this->tempDirs[] = $dir;
    file_put_contents($path = $dir->path('upload.zip'), layoutZip(['lay-read/plugin.json' => layoutManifest('lay-read')]));

    try {
        app(PluginService::class)->downloadPluginFromFile(new UploadedFile($path, 'upload.zip', 'application/zip', null, true));
        $this->fail('Expected the import to fail.');
    } catch (Exception $exception) {
        expectNoStagingPath($exception->getMessage());
        expect($exception->getMessage())->toBe('Could not read lay-read/plugin.json')
            // The full message is still there for the log
            ->and($exception->getPrevious()?->getMessage())->toContain('.import-');
    }

    expect(plugin_path('lay-read'))->not->toBeDirectory();
});
