<?php

use App\Enums\PluginStatus;
use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Jobs\Plugin\InstallPlugin;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\User;
use App\Services\Helpers\PluginRegistryConfig;
use App\Services\Helpers\PluginRegistryService;
use App\Services\Helpers\PluginService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use GuzzleHttp\Psr7\PumpStream;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Spatie\TemporaryDirectory\TemporaryDirectory;

use function Pest\Livewire\livewire;

// pelican/wings plugin contract v3, section R, for Panel plugins. All HTTP is faked.

const REGISTRY_URL = 'https://registry.example.test/registry.json';
const REGISTRY_HOST = 'https://registry.example.test';
const OTHER_REGISTRY_URL = 'https://other.example.test/registry.json';
const CDN_HOST = 'https://cdn.example.test';

beforeEach(function () {
    config()->set('panel.plugin.registries', []);
    config()->set('panel.plugin.trusted_keys');
    config()->set('panel.plugin.require_signatures', false);
});

afterEach(function () {
    Filament::setCurrentPanel(null);

    foreach ([...File::glob(plugin_path('reg-*')), ...File::glob(plugin_path('.reg-*')), ...File::glob(plugin_path('.import-*'))] as $dir) {
        File::deleteDirectory($dir);
    }

    Plugin::refreshRows();
});

/**
 * A plugin zip as a registry would serve it.
 *
 * @param  array<string, mixed>  $manifest
 */
function registryZip(string $id, string $version = '1.0.0', array $manifest = [], bool $symlink = false): string
{
    $dir = TemporaryDirectory::make();
    $path = $dir->path('plugin.zip');

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString("$id/plugin.json", json_encode([
        'id' => $id,
        'name' => 'Registry Test Plugin',
        'author' => 'Example',
        'version' => $version,
        'category' => 'plugin',
        'namespace' => 'RegistryTestPlugin',
        'class' => 'RegistryTestPlugin',
        ...$manifest,
    ]));

    if ($symlink) {
        $zip->addFromString("$id/escape", '/etc/passwd');
        $zip->setExternalAttributesName("$id/escape", ZipArchive::OPSYS_UNIX, 0120777 << 16);
    }

    $zip->close();

    $bytes = file_get_contents($path);
    $dir->delete();

    return $bytes;
}

/**
 * A `target: "panel"` registry entry for the given archive.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function panelEntry(string $name, string $archive, array $overrides = []): array
{
    return [
        'name' => $name,
        'version' => '1.0.0',
        'target' => 'panel',
        'author' => 'Example',
        'description' => 'A plugin from a registry',
        'download' => REGISTRY_HOST . "/plugins/$name.zip",
        'sha256' => hash('sha256', $archive),
        ...$overrides,
    ];
}

/**
 * Configures PANEL_PLUGIN_REGISTRIES the way the config file does, with the given environment.
 *
 * @param  array<string, mixed>  $registries
 * @param  array<string, string>  $env
 */
function configureRegistries(array $registries, array $env = []): void
{
    config()->set('panel.plugin.registries', PluginRegistryConfig::parse(json_encode($registries), fn (string $name) => $env[$name] ?? null));
}

function registries(): PluginRegistryService
{
    return app(PluginRegistryService::class);
}

/** @return array{public: string, secret: string} base64 public key and raw secret key, made up for the test */
function testSigningKey(): array
{
    $pair = sodium_crypto_sign_keypair();

    return ['public' => base64_encode(sodium_crypto_sign_publickey($pair)), 'secret' => sodium_crypto_sign_secretkey($pair)];
}

function signArchive(string $archive, string $secret): string
{
    return base64_encode(sodium_crypto_sign_detached(hash('sha256', $archive, true), $secret));
}

/** The recorded requests to URLs starting with the prefix. */
function requestsTo(string $prefix): array
{
    return collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (Request $request) => str_starts_with($request->url(), $prefix))
        ->values()
        ->all();
}

// R1: the document

it('uses only target panel entries, treats a missing target as wings and ignores unknown fields', function () {
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    $archive = registryZip('reg-panel');

    Http::fake([REGISTRY_URL => Http::response([
        'name' => 'Acme',
        'future_field' => ['anything'],
        'plugins' => [
            ['name' => 'reg-old', 'version' => '1.0.0', 'download' => REGISTRY_HOST . '/a.zip', 'sha256' => str_repeat('a', 64)],
            panelEntry('reg-wings', $archive, ['target' => 'wings', 'capability' => 'events', 'minWings' => '1.0.0']),
            panelEntry('reg-panel', $archive, ['unknown' => 'ignored', 'capability' => 'events', 'panel_version' => '^1.0']),
            'not an entry',
            ['version' => '1.0.0', 'target' => 'panel'],
        ],
    ])]);

    $plugin = registries()->resolve('@acme/reg-panel');

    expect($plugin->name)->toBe('reg-panel')
        ->and($plugin->version)->toBe('1.0.0')
        ->and($plugin->author)->toBe('Example')
        ->and($plugin->panelVersion)->toBe('^1.0')
        ->and($plugin->registryName)->toBe('Acme')
        ->and($plugin->source())->toBe('Acme, @acme (' . REGISTRY_URL . ')');

    expect(fn () => registries()->resolve('@acme/reg-old'))->toThrow(RuntimeException::class, 'doesn\'t list a Panel plugin named "reg-old"')
        ->and(fn () => registries()->resolve('@acme/reg-wings'))->toThrow(RuntimeException::class, 'doesn\'t list a Panel plugin named "reg-wings"');
});

it('accepts an empty plugins list but not a missing or null one', function () {
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    Http::fakeSequence(REGISTRY_URL)
        ->push(['name' => 'Acme', 'plugins' => []])
        ->push(['name' => 'Acme', 'plugins' => null])
        ->push(['name' => 'Acme']);

    expect(fn () => registries()->resolve('@acme/reg-x'))->toThrow(RuntimeException::class, 'doesn\'t list')
        ->and(fn () => registries()->resolve('@acme/reg-x'))->toThrow(RuntimeException::class, 'is not a plugin registry')
        ->and(fn () => registries()->resolve('@acme/reg-x'))->toThrow(RuntimeException::class, 'is not a plugin registry');
});

it('refuses an entry without a valid sha256, https download or safe name', function (array $overrides, string $name, string $reason) {
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    Http::fake([REGISTRY_URL => Http::response(['plugins' => [panelEntry($name, 'zip', $overrides)]])]);

    expect(fn () => registries()->resolve("@acme/$name"))->toThrow(RuntimeException::class, "has no valid $reason");
})->with([
    'missing sha256' => [['sha256' => null], 'reg-a', 'sha256'],
    'short sha256' => [['sha256' => 'abc'], 'reg-a', 'sha256'],
    'http download' => [['download' => 'http://registry.example.test/a.zip'], 'reg-a', 'download'],
    'missing version' => [['version' => ''], 'reg-a', 'version'],
    'name with a dot dot' => [[], 'reg..a', 'name'],
    'name starting with a dot' => [[], '.reg-a', 'name'],
    // R1: a signature that is present but not a non-empty string is invalid, never "unsigned".
    'empty signature' => [['signature' => ''], 'reg-a', 'signature'],
    'null signature' => [['signature' => null], 'reg-a', 'signature'],
    'numeric signature' => [['signature' => 5], 'reg-a', 'signature'],
    'list signature' => [['signature' => ['c2ln']], 'reg-a', 'signature'],
]);

// R2: fetching and downloading

it('sends the token and headers to the registry origin only', function () {
    configureRegistries(['acme' => [
        'url' => REGISTRY_URL,
        'token' => '${ACME_TOKEN}',
        'headers' => ['X-Api-Key' => '${ACME_KEY}'],
    ]], ['ACME_TOKEN' => 'test-token', 'ACME_KEY' => 'test-key']);

    Http::fake(['*' => Http::response('ok')]);

    registries()->fetch(REGISTRY_HOST . '/plugins/a.zip', 'acme', 1024);
    registries()->fetch(CDN_HOST . '/plugins/a.zip', 'acme', 1024);
    registries()->fetch('https://registry.example.test:8443/plugins/a.zip', 'acme', 1024);
    registries()->fetch('https://evil.registry.example.test/plugins/a.zip', 'acme', 1024);

    [$own] = requestsTo(REGISTRY_HOST . '/');
    expect($own->header('Authorization'))->toBe(['Bearer test-token'])
        ->and($own->header('X-Api-Key'))->toBe(['test-key']);

    foreach ([...requestsTo(CDN_HOST), ...requestsTo('https://registry.example.test:8443'), ...requestsTo('https://evil.')] as $request) {
        expect($request->hasHeader('Authorization'))->toBeFalse()
            ->and($request->hasHeader('X-Api-Key'))->toBeFalse();
    }

    expect(Http::recorded())->toHaveCount(4);
});

it('drops the credentials for the rest of a redirect chain once it leaves the registry host', function () {
    configureRegistries(['acme' => ['url' => REGISTRY_URL, 'token' => 'test-token']]);

    Http::fake([
        REGISTRY_HOST . '/start.zip' => Http::response('', 302, ['Location' => CDN_HOST . '/hop.zip']),
        CDN_HOST . '/hop.zip' => Http::response('', 302, ['Location' => REGISTRY_HOST . '/final.zip']),
        REGISTRY_HOST . '/final.zip' => Http::response('archive'),
    ]);

    expect(registries()->fetch(REGISTRY_HOST . '/start.zip', 'acme', 1024))->toBe('archive');

    $requests = collect(Http::recorded())->map(fn (array $pair) => $pair[0]);
    expect($requests->map(fn (Request $request) => $request->url())->all())->toBe([REGISTRY_HOST . '/start.zip', CDN_HOST . '/hop.zip', REGISTRY_HOST . '/final.zip'])
        ->and($requests->map(fn (Request $request) => $request->hasHeader('Authorization'))->all())->toBe([true, false, false]);
});

it('refuses a redirect to http and never requests it', function () {
    Http::fake([
        REGISTRY_URL => Http::response('', 301, ['Location' => 'http://registry.example.test/registry.json']),
        'http://*' => Http::response(['plugins' => []]),
    ]);

    expect(fn () => registries()->resolve(REGISTRY_URL . '#reg-a'))->toThrow(RuntimeException::class, 'must use https');

    Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'http://'));
});

it('refuses an http registry url', function () {
    Http::fake();

    expect(fn () => registries()->resolve('http://registry.example.test/registry.json#reg-a'))->toThrow(RuntimeException::class, 'must use https');

    Http::assertNothingSent();
});

it('follows at most 10 redirects', function () {
    Http::fake(['*' => fn (Request $request) => Http::response('', 302, ['Location' => $request->url() . 'x'])]);

    expect(fn () => registries()->fetch(REGISTRY_HOST . '/r', null, 1024))->toThrow(RuntimeException::class, 'Stopped following redirects');
    expect(Http::recorded())->toHaveCount(11);
});

/**
 * A body of `$size` bytes without a Content-Length, produced as it is read; `$read` counts the bytes read so far.
 */
function unsizedBody(int $size, int &$read): PumpStream
{
    $read = 0;

    return new PumpStream(function (int $length) use ($size, &$read) {
        $length = min($length, $size - $read);
        if ($length <= 0) {
            return false;
        }

        $read += $length;

        return str_repeat('a', $length);
    });
}

it('stops reading a registry response without Content-Length once it passes the size cap', function () {
    $read = 0;
    Http::fake([REGISTRY_URL => Http::response(unsizedBody(8 * 1024 * 1024, $read))]);

    expect(fn () => registries()->fetch(REGISTRY_URL, null, 1024 * 1024))->toThrow(RuntimeException::class, 'is too large');
    expect($read)->toBeGreaterThan(1024 * 1024)
        ->toBeLessThanOrEqual(1024 * 1024 + 64 * 1024);
});

it('stops reading a plugin download without Content-Length once it passes the size cap', function () {
    config()->set('panel.plugin.max_import_size', 1024 * 1024);

    $read = 0;
    Http::fake(['https://downloads.example.test/*' => Http::response(unsizedBody(8 * 1024 * 1024, $read))]);

    expect(fn () => app(PluginService::class)->downloadPluginFromUrl('https://downloads.example.test/reg-big.zip'))->toThrow(Exception::class, 'too large');
    expect($read)->toBeGreaterThan(1024 * 1024)
        ->toBeLessThanOrEqual(1024 * 1024 + 64 * 1024);
});

it('refuses a response whose Content-Length is over the size cap without reading it', function () {
    $read = 0;
    Http::fake([REGISTRY_URL => Http::response(unsizedBody(1024, $read), 200, ['Content-Length' => (string) (2 * 1024 * 1024)])]);

    expect(fn () => registries()->fetch(REGISTRY_URL, null, 1024 * 1024))->toThrow(RuntimeException::class, 'is too large');
    expect($read)->toBe(0);
});

it('reads a registry response that fits the size cap whole', function () {
    $read = 0;
    Http::fake([REGISTRY_URL => Http::response(unsizedBody(1024 * 1024, $read))]);

    expect(registries()->fetch(REGISTRY_URL, null, 1024 * 1024))->toBe(str_repeat('a', 1024 * 1024));
});

it('gives the whole redirect chain one deadline instead of a fresh timeout per hop', function () {
    $this->freezeTime();

    $timeouts = [];
    Http::fake(['*' => function (Request $request, array $options) use (&$timeouts) {
        $timeouts[] = round($options['timeout'], 3);
        $this->travel(6)->seconds();

        return Http::response('', 302, ['Location' => $request->url() . 'x']);
    }]);

    expect(fn () => registries()->fetch(REGISTRY_HOST . '/r', null, 1024))->toThrow(RuntimeException::class, 'timed out');
    expect($timeouts)->toEqual([15.0, 9.0, 3.0]);
});

it('fails a body that finishes arriving after the deadline', function () {
    $this->freezeTime();

    $chunks = 0;
    Http::fake([REGISTRY_URL => Http::response(new PumpStream(function () use (&$chunks) {
        if ($chunks++ === 2) {
            return false;
        }

        $this->travel(10)->seconds();

        return 'a';
    }))]);

    expect(fn () => registries()->fetch(REGISTRY_URL, null, 1024))->toThrow(RuntimeException::class, 'timed out');
});

it('never sends credentials for url#name, even to a configured registry', function () {
    configureRegistries(['acme' => ['url' => REGISTRY_URL, 'token' => 'test-token']]);

    $archive = registryZip('reg-url');
    Http::fake([REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-url', $archive)]])]);

    $plugin = registries()->resolve(REGISTRY_URL . '#reg-url');

    expect($plugin->namespace)->toBeNull()
        ->and($plugin->reference())->toBe(REGISTRY_URL . '#reg-url');
    Http::assertSent(fn (Request $request) => !$request->hasHeader('Authorization'));
});

it('refuses an archive whose sha256 does not match before extracting it', function () {
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    $archive = registryZip('reg-tampered');
    Http::fake([
        REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-tampered', $archive, ['sha256' => hash('sha256', 'something else')])]]),
        REGISTRY_HOST . '/plugins/*' => Http::response($archive),
    ]);

    $plugin = registries()->resolve('@acme/reg-tampered');

    expect(fn () => app(PluginService::class)->downloadPluginFromRegistry($plugin))->toThrow(Exception::class, 'does not match the checksum');
    expect(plugin_path('reg-tampered'))->not->toBeDirectory()
        ->and(File::glob(plugin_path('.import-*')))->toBe([]);
});

it('installs a signed archive whose signature verifies against a trusted key', function () {
    $key = testSigningKey();
    config()->set('panel.plugin.trusted_keys', 'other:' . base64_encode(random_bytes(32)) . ', release:' . $key['public']);
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    $archive = registryZip('reg-signed');
    Http::fake([
        REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-signed', $archive, ['signature' => signArchive($archive, $key['secret'])])]]),
        REGISTRY_HOST . '/plugins/*' => Http::response($archive),
    ]);

    expect(app(PluginService::class)->downloadPluginFromRegistry(registries()->resolve('@acme/reg-signed')))->toBe('reg-signed')
        ->and(plugin_path('reg-signed', 'plugin.json'))->toBeFile();
});

it('refuses a signature that does not verify, even when signatures are not required', function (?string $trustedKeys) {
    config()->set('panel.plugin.trusted_keys', $trustedKeys);
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    $archive = registryZip('reg-forged');
    Http::fake([
        REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-forged', $archive, ['signature' => signArchive($archive, testSigningKey()['secret'])])]]),
        REGISTRY_HOST . '/plugins/*' => Http::response($archive),
    ]);

    expect(fn () => app(PluginService::class)->downloadPluginFromRegistry(registries()->resolve('@acme/reg-forged')))->toThrow(RuntimeException::class, 'does not match any trusted key');
    expect(plugin_path('reg-forged'))->not->toBeDirectory();
})->with([
    'another trusted key' => fn () => testSigningKey()['public'],
    'no trusted keys' => null,
]);

it('refuses an unsigned archive only when signatures are required', function () {
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    $archive = registryZip('reg-unsigned');
    Http::fake([
        REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-unsigned', $archive)]]),
        REGISTRY_HOST . '/plugins/*' => Http::response($archive),
    ]);

    $plugin = registries()->resolve('@acme/reg-unsigned');

    config()->set('panel.plugin.require_signatures', true);
    expect(fn () => app(PluginService::class)->downloadPluginFromRegistry($plugin))->toThrow(RuntimeException::class, 'requires signed plugins');
    expect(plugin_path('reg-unsigned'))->not->toBeDirectory();

    config()->set('panel.plugin.require_signatures', false);
    expect(app(PluginService::class)->downloadPluginFromRegistry($plugin))->toBe('reg-unsigned');
});

it('refuses an archive that is not exactly what the entry says', function (string $archive, string $message) {
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    Http::fake([
        REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-mismatch', $archive)]]),
        REGISTRY_HOST . '/plugins/*' => Http::response($archive),
    ]);

    expect(fn () => app(PluginService::class)->downloadPluginFromRegistry(registries()->resolve('@acme/reg-mismatch')))->toThrow(Exception::class, $message);
    expect(plugin_path('reg-mismatch'))->not->toBeDirectory();
})->with([
    'another plugin' => fn () => [registryZip('reg-other'), 'is for plugin "reg-other"'],
    'another version' => fn () => [registryZip('reg-mismatch', '2.0.0'), 'is version "2.0.0", not "1.0.0"'],
    'a symlink' => fn () => [registryZip('reg-mismatch', symlink: true), 'symbolic link'],
    'a tar.gz' => fn () => [gzencode('tar'), 'Panel plugins must be .zip archives (plugin contract v3, R1)'],
    'not an archive' => fn () => ['<html>', 'not a zip'],
]);

it('counts the directories extraction would create against the entry limit, not just the zip records', function () {
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    // 12 records, but extractTo() would create 11 x 1900 nested directories for them: 20,901 entries.
    $dir = TemporaryDirectory::make();
    $zip = new ZipArchive();
    $zip->open($dir->path('plugin.zip'), ZipArchive::CREATE);
    $zip->addFromString('reg-deep/plugin.json', json_encode(['id' => 'reg-deep', 'name' => 'Deep', 'author' => 'Example', 'version' => '1.0.0', 'category' => 'plugin', 'namespace' => 'Deep', 'class' => 'Deep']));
    for ($i = 0; $i < 11; $i++) {
        $zip->addFromString("reg-deep/t$i/" . str_repeat('d/', 1899) . 'f', '');
    }
    $zip->close();
    $archive = file_get_contents($dir->path('plugin.zip'));
    $dir->delete();

    Http::fake([
        REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-deep', $archive)]]),
        REGISTRY_HOST . '/plugins/*' => Http::response($archive),
    ]);

    expect(fn () => app(PluginService::class)->downloadPluginFromRegistry(registries()->resolve('@acme/reg-deep')))->toThrow(Exception::class, 'more than 20000 entries');
    expect(plugin_path('reg-deep'))->not->toBeDirectory()
        ->and(File::glob(plugin_path('.import-*')))->toBe([]);
});

it('refuses a plugin that needs another panel version before downloading it', function () {
    config()->set('app.version', '1.2.0');
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    $archive = registryZip('reg-future');
    Http::fake([REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-future', $archive, ['panel_version' => '^2.0'])]])]);

    expect(fn () => app(PluginService::class)->downloadPluginFromRegistry(registries()->resolve('@acme/reg-future')))->toThrow(Exception::class, 'needs Panel version ^2.0');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/plugins/'));
});

it('reads ${NAME} from the environment and never keeps a registry with an unset one usable', function () {
    $registries = PluginRegistryConfig::parse(json_encode([
        'acme' => ['url' => REGISTRY_URL, 'token' => 'pre-${ACME_TOKEN}', 'headers' => ['X-Key' => '${ACME_KEY}']],
        'broken' => ['url' => OTHER_REGISTRY_URL, 'token' => '${MISSING_TOKEN}'],
        'plain' => ['url' => 'http://insecure.example.test/registry.json'],
        'Bad Namespace' => ['url' => REGISTRY_URL],
    ]), fn (string $name) => ['ACME_TOKEN' => 'test-token', 'ACME_KEY' => 'test-key'][$name] ?? null);

    expect($registries['acme'])->toBe(['url' => REGISTRY_URL, 'token' => 'pre-test-token', 'headers' => ['X-Key' => 'test-key'], 'error' => null])
        ->and($registries['broken']['error'])->toBe('Environment variable(s) not set: MISSING_TOKEN.')
        ->and($registries['broken']['token'])->toBeNull()
        ->and($registries['plain']['error'])->toBe('The registry url must be https.')
        ->and($registries['Bad Namespace']['error'])->toContain('namespace');

    expect(PluginRegistryConfig::parse(null, fn () => null))->toBe([])
        ->and(PluginRegistryConfig::parse('', fn () => null))->toBe([])
        ->and(PluginRegistryConfig::parse('{not json', fn () => null))->toBeString()
        ->and(PluginRegistryConfig::parse('[{"url": "https://x"}]', fn () => null))->toBeString();
});

it('accepts numeric namespaces like "0", which a JSON object may use as keys', function () {
    $registries = PluginRegistryConfig::parse('{"0": {"url": "' . REGISTRY_URL . '"}, "1": {"url": "' . OTHER_REGISTRY_URL . '"}}', fn () => null);

    expect($registries)->toBeArray()
        ->and($registries['0']['url'])->toBe(REGISTRY_URL)
        ->and($registries['1']['url'])->toBe(OTHER_REGISTRY_URL);

    config()->set('panel.plugin.registries', $registries);

    $archive = registryZip('reg-zero');
    Http::fake([
        REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-zero', $archive)]]),
        OTHER_REGISTRY_URL => Http::response(['plugins' => []]),
    ]);

    expect(registries()->resolve('@0/reg-zero')->reference())->toBe('@0/reg-zero')
        ->and(registries()->resolve('reg-zero')->reference())->toBe('@0/reg-zero');

    // A JSON list is still not a registry object, even though it decodes to the same PHP keys.
    expect(PluginRegistryConfig::parse('[{"url": "' . REGISTRY_URL . '"}]', fn () => null))->toBeString()
        ->and(PluginRegistryConfig::parse('[]', fn () => null))->toBe([])
        ->and(PluginRegistryConfig::parse('{}', fn () => null))->toBe([])
        ->and(PluginRegistryConfig::parse('"acme"', fn () => null))->toBeString();
});

it('refuses to use a registry whose config is broken', function () {
    config()->set('panel.plugin.registries', PluginRegistryConfig::parse('{not json', fn () => null));
    Http::fake();

    expect(registries()->hasRegistries())->toBeTrue()
        ->and(fn () => registries()->resolve('reg-a'))->toThrow(RuntimeException::class, 'must be a JSON object');

    Http::assertNothingSent();
});

// R3: choosing a registry

it('refuses a bare name that more than one registry lists, and installs it by @namespace/name', function () {
    configureRegistries(['acme' => ['url' => REGISTRY_URL], 'other' => ['url' => OTHER_REGISTRY_URL]]);

    $archive = registryZip('reg-dup');
    Http::fake([
        REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-dup', $archive)]]),
        OTHER_REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-dup', $archive, ['download' => 'https://other.example.test/reg-dup.zip'])]]),
    ]);

    expect(fn () => registries()->resolve('reg-dup'))->toThrow(RuntimeException::class, 'Choose one of: @acme/reg-dup, @other/reg-dup');

    expect(registries()->resolve('@other/reg-dup')->download)->toBe('https://other.example.test/reg-dup.zip');
});

it('resolves a bare name listed by exactly one registry, ignoring Wings entries elsewhere', function () {
    configureRegistries(['acme' => ['url' => REGISTRY_URL], 'other' => ['url' => OTHER_REGISTRY_URL]]);

    $archive = registryZip('reg-one');
    Http::fake([
        REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-one', $archive, ['target' => 'wings'])]]),
        OTHER_REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-one', $archive)]]),
    ]);

    expect(registries()->resolve('reg-one')->reference())->toBe('@other/reg-one');
});

it('refuses a bare name while any registry can\'t be read, but still installs by @namespace/name', function (Closure $breakAcme, string $message) {
    $breakAcme();

    $archive = registryZip('reg-hidden');
    Http::fake([
        REGISTRY_URL => Http::response('down', 500),
        OTHER_REGISTRY_URL => Http::response(['plugins' => [panelEntry('reg-hidden', $archive)]]),
    ]);

    expect(fn () => registries()->resolve('reg-hidden'))->toThrow(RuntimeException::class, $message);
    expect(registries()->resolve('@other/reg-hidden')->name)->toBe('reg-hidden');
})->with([
    'a server error' => [fn () => configureRegistries(['acme' => ['url' => REGISTRY_URL], 'other' => ['url' => OTHER_REGISTRY_URL]]), 'Could not read the plugin registry "acme"'],
    'an unset variable' => [fn () => configureRegistries(['acme' => ['url' => REGISTRY_URL, 'token' => '${UNSET}'], 'other' => ['url' => OTHER_REGISTRY_URL]]), 'not set: UNSET'],
]);

it('refuses an unknown namespace, a malformed reference and a bare name without registries', function () {
    Http::fake();

    expect(fn () => registries()->resolve('reg-a'))->toThrow(RuntimeException::class, 'No plugin registries are configured');

    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    expect(fn () => registries()->resolve('@nope/reg-a'))->toThrow(RuntimeException::class, 'namespace "nope"')
        ->and(fn () => registries()->resolve('@acme'))->toThrow(RuntimeException::class, 'not a valid plugin reference');

    Http::assertNothingSent();
});

// R4: update manifests

function updatablePlugin(string $id, string $updateUrl): Plugin
{
    config()->set('app.version', '1.0.0');

    File::ensureDirectoryExists(plugin_path($id));
    File::put(plugin_path($id, 'plugin.json'), json_encode([
        'id' => $id,
        'name' => 'Registry Test Plugin',
        'author' => 'Example',
        'version' => '1.0.0',
        'category' => 'plugin',
        'update_url' => $updateUrl,
        'namespace' => 'RegistryTestPlugin',
        'class' => 'RegistryTestPlugin',
    ]));

    Plugin::refreshRows();

    return Plugin::findOrFail($id);
}

it('reads update manifests in the plain shape and the Hub shape', function (array $manifest, ?string $signature) {
    $plugin = updatablePlugin('reg-update', 'https://updates.example.test/reg-update.json');

    Http::fake(['https://updates.example.test/*' => Http::response($manifest)]);

    expect($plugin->isUpdateAvailable())->toBeTrue()
        ->and($plugin->getUpdateDownload())->toBe(['url' => 'https://updates.example.test/reg-update-2.0.0.zip', 'sha256' => str_repeat('b', 64), 'signature' => $signature]);
})->with([
    'plain' => [['version' => '2.0.0', 'download' => 'https://updates.example.test/reg-update-2.0.0.zip', 'sha256' => str_repeat('b', 64), 'signature' => 'c2ln', 'future' => true], 'c2ln'],
    'hub' => [['*' => ['version' => '2.0.0', 'download_url' => 'https://updates.example.test/reg-update-2.0.0.zip', 'channel' => 'stable', 'sha256' => str_repeat('b', 64)]], null],
    'keyed by plugin id' => [['reg-update' => ['version' => '2.0.0', 'download' => 'https://updates.example.test/reg-update-2.0.0.zip', 'sha256' => str_repeat('b', 64)]], null],
]);

it('treats an empty update manifest as no release', function (mixed $manifest) {
    $plugin = updatablePlugin('reg-update', 'https://updates.example.test/reg-update.json');

    Http::fake(['https://updates.example.test/*' => Http::response($manifest)]);

    expect($plugin->isUpdateAvailable())->toBeFalse()
        ->and($plugin->getUpdateDownload())->toBeNull();
})->with([
    'empty list' => [[]],
    'empty object' => ['{}'],
    'not an object' => ['"nope"'],
]);

it('verifies the signature of a signed update', function () {
    Process::fake();
    $key = testSigningKey();
    config()->set('panel.plugin.trusted_keys', $key['public']);

    $plugin = updatablePlugin('reg-update', 'https://updates.example.test/reg-update.json');
    $archive = registryZip('reg-update', '2.0.0', ['update_url' => 'https://updates.example.test/reg-update.json']);

    $signature = signArchive($archive, testSigningKey()['secret']);
    Http::fake([
        'https://updates.example.test/reg-update.json' => function () use (&$signature, $archive) {
            return Http::response(['version' => '2.0.0', 'download' => 'https://updates.example.test/reg-update-2.0.0.zip', 'sha256' => hash('sha256', $archive), 'signature' => $signature]);
        },
        'https://updates.example.test/reg-update-2.0.0.zip' => Http::response($archive),
    ]);

    expect(fn () => app(PluginService::class)->updatePlugin($plugin))->toThrow(RuntimeException::class, 'does not match any trusted key');
    expect(File::json(plugin_path('reg-update', 'plugin.json'))['version'])->toBe('1.0.0');

    cache()->forget('plugins.reg-update.update');
    $signature = signArchive($archive, $key['secret']);

    expect(app(PluginService::class)->updatePlugin($plugin)->version)->toBe('2.0.0');
});

it('refuses an update whose manifest has a malformed signature, even when signatures are not required', function (mixed $signature) {
    Process::fake();

    $plugin = updatablePlugin('reg-update', 'https://updates.example.test/reg-update.json');
    $archive = registryZip('reg-update', '2.0.0', ['update_url' => 'https://updates.example.test/reg-update.json']);

    Http::fake([
        'https://updates.example.test/reg-update.json' => Http::response(['version' => '2.0.0', 'download' => 'https://updates.example.test/reg-update-2.0.0.zip', 'sha256' => hash('sha256', $archive), 'signature' => $signature]),
        'https://updates.example.test/reg-update-2.0.0.zip' => Http::response($archive),
    ]);

    expect(fn () => app(PluginService::class)->updatePlugin($plugin))->toThrow(RuntimeException::class, 'signature is malformed');
    expect(File::json(plugin_path('reg-update', 'plugin.json'))['version'])->toBe('1.0.0');
})->with([
    'empty' => [''],
    'null' => [null],
    'a number' => [5],
]);

// R2.5: "require signatures" covers every install path, uploads and url imports included

function uploadedZip(string $archive): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $archive);

    return new UploadedFile($path, 'plugin.zip', 'application/zip', null, true);
}

it('applies the signature policy to uploaded archives', function () {
    $key = testSigningKey();
    config()->set('panel.plugin.trusted_keys', $key['public']);
    $archive = registryZip('reg-upload');

    // Signatures not required: an unsigned upload imports as before.
    expect(app(PluginService::class)->downloadPluginFromFile(uploadedZip($archive)))->toBe('reg-upload');
    File::deleteDirectory(plugin_path('reg-upload'));

    // A signature that doesn't verify is refused either way.
    expect(fn () => app(PluginService::class)->downloadPluginFromFile(uploadedZip($archive), signature: signArchive($archive, testSigningKey()['secret'])))->toThrow(RuntimeException::class, 'does not match any trusted key');

    config()->set('panel.plugin.require_signatures', true);

    expect(fn () => app(PluginService::class)->downloadPluginFromFile(uploadedZip($archive)))->toThrow(RuntimeException::class, 'requires signed plugins');
    expect(plugin_path('reg-upload'))->not->toBeDirectory();

    expect(app(PluginService::class)->downloadPluginFromFile(uploadedZip($archive), signature: signArchive($archive, $key['secret'])))->toBe('reg-upload');
});

it('applies the signature policy to url imports, with or without a checksum', function () {
    $key = testSigningKey();
    config()->set('panel.plugin.trusted_keys', $key['public']);
    $archive = registryZip('reg-url-import');
    $url = 'https://downloads.example.test/reg-url-import.zip';
    Http::fake(['https://downloads.example.test/*' => Http::response($archive)]);

    // Signatures not required: an unsigned url import works as before.
    expect(app(PluginService::class)->downloadPluginFromUrl($url))->toBe('reg-url-import');
    File::deleteDirectory(plugin_path('reg-url-import'));

    // A signature that doesn't verify is refused, even without a checksum to go with it.
    expect(fn () => app(PluginService::class)->downloadPluginFromUrl($url, signature: signArchive($archive, testSigningKey()['secret'])))->toThrow(RuntimeException::class, 'does not match any trusted key');

    config()->set('panel.plugin.require_signatures', true);

    expect(fn () => app(PluginService::class)->downloadPluginFromUrl($url))->toThrow(RuntimeException::class, 'requires signed plugins');
    expect(plugin_path('reg-url-import'))->not->toBeDirectory();

    expect(app(PluginService::class)->downloadPluginFromUrl($url, signature: signArchive($archive, $key['secret'])))->toBe('reg-url-import');
});

it('refuses an unsigned update when signatures are required, even without a checksum in the feed', function () {
    Process::fake();
    config()->set('panel.plugin.require_signatures', true);

    $plugin = updatablePlugin('reg-update', 'https://updates.example.test/reg-update.json');
    $archive = registryZip('reg-update', '2.0.0');

    Http::fake([
        'https://updates.example.test/reg-update.json' => Http::response(['version' => '2.0.0', 'download' => 'https://updates.example.test/reg-update-2.0.0.zip']),
        'https://updates.example.test/reg-update-2.0.0.zip' => Http::response($archive),
    ]);

    expect(fn () => app(PluginService::class)->updatePlugin($plugin))->toThrow(RuntimeException::class, 'requires signed plugins');
    expect(File::json(plugin_path('reg-update', 'plugin.json'))['version'])->toBe('1.0.0');
});

// The install command

it('shows the registry entry and installs it only after the admin confirms', function () {
    Process::fake();
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    $archive = registryZip('reg-cli');
    Http::fake([
        REGISTRY_URL => Http::response(['name' => 'Acme', 'plugins' => [panelEntry('reg-cli', $archive)]]),
        REGISTRY_HOST . '/plugins/*' => Http::response($archive),
    ]);

    $question = 'Install this plugin? It runs inside the panel, so only install plugins from authors you trust.';

    $this->artisan('p:plugin:install', ['id' => '@acme/reg-cli'])
        ->expectsOutputToContain('Acme, @acme (' . REGISTRY_URL . ')')
        ->expectsConfirmation($question, 'no')
        ->expectsOutput('Nothing was installed.')
        ->assertSuccessful();

    expect(plugin_path('reg-cli'))->not->toBeDirectory();
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/plugins/'));

    $this->artisan('p:plugin:install', ['id' => 'reg-cli'])
        ->expectsConfirmation($question, 'yes')
        ->expectsOutput('Plugin installed and enabled.')
        ->assertSuccessful();

    Plugin::refreshRows();
    expect(Plugin::findOrFail('reg-cli')->status)->toBe(PluginStatus::Enabled);
});

it('keeps the install command as it was for panels without registries', function () {
    Http::fake();

    $this->artisan('p:plugin:install', ['id' => 'reg-not-here'])
        ->expectsOutput('Plugin does not exist!')
        ->assertSuccessful();

    Http::assertNothingSent();
});

// The admin UI

function registryAdmin(): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create();
    test()->actingAs($admin->syncRoles(Role::getRootAdmin()));
}

it('only offers registry installs when a registry is configured', function () {
    registryAdmin();

    livewire(ListPlugins::class)->assertActionHidden(TestAction::make('install_from_registry')->table());

    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    livewire(ListPlugins::class)->assertActionVisible(TestAction::make('install_from_registry')->table());
});

it('installs from a registry what the admin reviewed, and nothing else', function () {
    Queue::fake();
    registryAdmin();
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    $archive = registryZip('reg-ui');
    $entry = panelEntry('reg-ui', $archive);
    Http::fake([
        REGISTRY_URL => function () use (&$entry) {
            return Http::response(['name' => 'Acme', 'plugins' => [$entry]]);
        },
        REGISTRY_HOST . '/plugins/*' => Http::response($archive),
    ]);

    $approved = [
        'name' => 'reg-ui',
        'version' => '1.0.0',
        'author' => 'Example',
        'description' => 'A plugin from a registry',
        'source' => 'Acme, @acme (' . REGISTRY_URL . ')',
        'download' => REGISTRY_HOST . '/plugins/reg-ui.zip',
        'sha256' => hash('sha256', $archive),
        'signed' => 'no',
        'panel_version' => null,
    ];

    // The registry changed after the admin reviewed it: nothing is downloaded.
    $entry = panelEntry('reg-ui', $archive, ['version' => '1.0.1']);

    livewire(ListPlugins::class)
        ->callAction(TestAction::make('install_from_registry')->table(), data: ['reference' => '@acme/reg-ui', 'approved' => $approved])
        ->assertNotified(trans('admin/plugin.notifications.install_error'));

    expect(plugin_path('reg-ui'))->not->toBeDirectory();
    Queue::assertNothingPushed();

    $entry = panelEntry('reg-ui', $archive);

    livewire(ListPlugins::class)
        ->callAction(TestAction::make('install_from_registry')->table(), data: ['reference' => '@acme/reg-ui', 'approved' => $approved])
        ->assertNotified(trans('admin/plugin.notifications.install_started'));

    expect(plugin_path('reg-ui', 'plugin.json'))->toBeFile();
    Queue::assertPushed(InstallPlugin::class, fn (InstallPlugin $job) => $job->pluginId === 'reg-ui' && !$job->reinstall);
});

it('asks for a signature on imports only when trusted keys are configured, and checks it', function () {
    registryAdmin();

    $key = testSigningKey();
    $archive = registryZip('reg-ui-import');
    $url = 'https://downloads.example.test/reg-ui-import.zip';
    Http::fake(['https://downloads.example.test/*' => Http::response($archive)]);

    // Without trusted keys the import forms stay as they were.
    livewire(ListPlugins::class)
        ->mountAction(TestAction::make('import_from_url')->table())
        ->assertSchemaComponentHidden('signature');

    config()->set('panel.plugin.trusted_keys', $key['public']);
    config()->set('panel.plugin.require_signatures', true);

    livewire(ListPlugins::class)
        ->mountAction(TestAction::make('import_from_url')->table())
        ->assertSchemaComponentVisible('signature');

    livewire(ListPlugins::class)
        ->callAction(TestAction::make('import_from_url')->table(), data: ['url' => $url])
        ->assertNotified(trans('admin/plugin.notifications.import_failed'));

    expect(plugin_path('reg-ui-import'))->not->toBeDirectory();

    livewire(ListPlugins::class)
        ->callAction(TestAction::make('import_from_url')->table(), data: ['url' => $url, 'signature' => signArchive($archive, $key['secret'])])
        ->assertNotified(trans('admin/plugin.notifications.imported'));

    expect(plugin_path('reg-ui-import', 'plugin.json'))->toBeFile();
});

it('walks the admin through resolving, reviewing and installing a registry plugin', function () {
    Queue::fake();
    registryAdmin();
    configureRegistries(['acme' => ['url' => REGISTRY_URL]]);

    $archive = registryZip('reg-wizard');
    Http::fake([
        REGISTRY_URL => Http::response(['name' => 'Acme', 'plugins' => [panelEntry('reg-wizard', $archive, ['author' => 'Wizard Author'])]]),
        REGISTRY_HOST . '/plugins/*' => Http::response($archive),
    ]);

    livewire(ListPlugins::class)
        ->mountAction(TestAction::make('install_from_registry')->table())
        ->fillForm(['reference' => '@acme/nope'])
        ->goToNextWizardStep()
        ->assertNotified(trans('admin/plugin.notifications.install_error'))
        ->assertWizardCurrentStep(1)
        ->fillForm(['reference' => '@acme/reg-wizard'])
        ->goToNextWizardStep()
        ->assertWizardCurrentStep(2)
        // The review step shows what the server resolved, before anything is downloaded.
        ->assertSchemaComponentStateSet('approved_author', 'Wizard Author')
        ->assertSchemaComponentStateSet('approved_source', 'Acme, @acme (' . REGISTRY_URL . ')')
        ->assertSchemaComponentStateSet('approved_sha256', hash('sha256', $archive))
        ->assertSchemaComponentStateSet('approved_signed', trans('admin/plugin.registry.unsigned'))
        ->assertSchemaComponentHidden('approved_replaces')
        ->tap(fn () => Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/plugins/')))
        ->callMountedAction()
        ->assertNotified(trans('admin/plugin.notifications.install_started'));

    expect(plugin_path('reg-wizard', 'plugin.json'))->toBeFile();
    Queue::assertPushed(InstallPlugin::class, fn (InstallPlugin $job) => $job->pluginId === 'reg-wizard');
});
