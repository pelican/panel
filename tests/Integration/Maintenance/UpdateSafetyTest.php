<?php

use App\Data\UpdateSnapshotData;
use App\Services\Helpers\SoftwareVersionService;
use App\Services\Maintenance\UpdateCompatibilityService;
use App\Services\Maintenance\UpdateSnapshotService;
use App\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Spatie\TemporaryDirectory\TemporaryDirectory;

uses(TestCase::class);

covers(UpdateCompatibilityService::class);
covers(UpdateSnapshotService::class);
covers(UpdateSnapshotData::class);

final class UpdateSafetyTestDirectories
{
    /** @var list<TemporaryDirectory> */
    public static array $directories = [];
}

beforeEach(function () {
    UpdateSafetyTestDirectories::$directories = [];
    $this->originalDatabaseDefault = config('database.default');
});

afterEach(function () {
    DB::purge('snapshot_test');
    config()->set('database.default', $this->originalDatabaseDefault);

    foreach (UpdateSafetyTestDirectories::$directories as $directory) {
        $directory->delete();
    }
});

/**
 * Create and register a temporary directory for automatic cleanup after each test.
 */
function updateSafetyDirectory(): TemporaryDirectory
{
    $directory = TemporaryDirectory::make();
    UpdateSafetyTestDirectories::$directories[] = $directory;

    return $directory;
}

/**
 * Configure a named SQLite connection used by the snapshot service tests.
 */
function configureSnapshotSqlite(string $databasePath): void
{
    config()->set([
        'database.default' => 'snapshot_test',
        'database.connections.snapshot_test' => [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
        ],
    ]);
    DB::purge('snapshot_test');
}

it('checks the target lock file with an explicitly resolved Composer PHAR', function () {
    $source = updateSafetyDirectory();
    $composer = $source->path('composer.phar');
    File::put($source->path('composer.json'), '{}');
    File::put($source->path('composer.lock'), '{}');
    File::put($composer, 'composer fixture');
    Process::fake(['*' => Process::result(output: 'All platform requirements satisfied.')]);

    app(UpdateCompatibilityService::class)->assertCompatible($source->path(), $composer);

    Process::assertRan(fn ($process) => $process->path === $source->path()
        && $process->command === [
            PHP_BINARY,
            $composer,
            'check-platform-reqs',
            '--lock',
            '--no-dev',
            '--no-interaction',
        ]);
});

it('requires a valid explicit Composer executable', function () {
    $source = updateSafetyDirectory();
    File::put($source->path('composer.json'), '{}');
    File::put($source->path('composer.lock'), '{}');

    expect(fn () => app(UpdateCompatibilityService::class)
        ->assertCompatible($source->path(), $source->path('missing-composer')))
        ->toThrow(RuntimeException::class, 'could not be found');
});

it('captures named SQLite connections including committed WAL data', function () {
    $root = updateSafetyDirectory();
    $environmentPath = $root->path('.env');
    $databasePath = $root->path('database.sqlite');
    File::put($environmentPath, "APP_ENV=testing\n");

    $database = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database->exec('PRAGMA journal_mode = WAL');
    $database->exec('PRAGMA wal_autocheckpoint = 0');
    $database->exec('CREATE TABLE settings (name TEXT NOT NULL)');
    $database->exec("INSERT INTO settings VALUES ('committed in WAL')");
    expect($databasePath . '-wal')->toBeFile();

    configureSnapshotSqlite($databasePath);

    $snapshot = app(UpdateSnapshotService::class)->capture(
        targetVersion: 'v1.2.3',
        snapshotRoot: $root->path('snapshots'),
        environmentPath: $environmentPath,
    );
    $backup = new PDO('sqlite:' . $snapshot->path . DIRECTORY_SEPARATOR . 'database.sqlite');
    $metadata = File::json($snapshot->path . DIRECTORY_SEPARATOR . 'metadata.json');
    $rollback = File::get($snapshot->rollbackGuide);

    expect($snapshot)->toBeInstanceOf(UpdateSnapshotData::class)
        ->and($backup->query('SELECT name FROM settings')->fetchColumn())->toBe('committed in WAL')
        ->and($metadata['database_driver'])->toBe('sqlite')
        ->and($metadata['target_version'])->toBe('v1.2.3')
        ->and($rollback)->toContain($snapshot->path . DIRECTORY_SEPARATOR . '.env')
        ->and($rollback)->toContain($environmentPath)
        ->and($rollback)->toContain('p:maintenance:finish-update');
});

it('retains only the configured number of protected snapshots', function () {
    $root = updateSafetyDirectory();
    $environmentPath = $root->path('.env');
    $databasePath = $root->path('database.sqlite');
    File::put($environmentPath, "APP_ENV=testing\n");
    $database = new PDO('sqlite:' . $databasePath);
    $database->exec('CREATE TABLE settings (name TEXT NOT NULL)');
    $database = null;
    configureSnapshotSqlite($databasePath);

    $service = app(UpdateSnapshotService::class);
    $service->capture(snapshotRoot: $root->path('snapshots'), environmentPath: $environmentPath, retainedSnapshots: 2);
    $service->capture(snapshotRoot: $root->path('snapshots'), environmentPath: $environmentPath, retainedSnapshots: 2);
    $latest = $service->capture(snapshotRoot: $root->path('snapshots'), environmentPath: $environmentPath, retainedSnapshots: 2);

    expect(File::directories($root->path('snapshots')))->toHaveCount(2)
        ->and($latest->path)->toBeDirectory()
        ->and($service->latest($root->path('snapshots')))->not->toBeNull();
});

it('rejects failed required writes and removes the partial snapshot', function () {
    $root = updateSafetyDirectory();
    $environmentPath = $root->path('.env');
    File::put($environmentPath, "APP_ENV=testing\n");

    $files = new class extends Filesystem
    {
        public function copy($path, $target)
        {
            return false;
        }
    };
    $service = new UpdateSnapshotService(app(SoftwareVersionService::class), $files);

    expect(fn () => $service->capture(
        snapshotRoot: $root->path('snapshots'),
        environmentPath: $environmentPath,
    ))->toThrow(RuntimeException::class, 'could not be written');

    expect(File::directories($root->path('snapshots')))->toBeEmpty();
});

it('does not expose incomplete snapshot paths for rollback', function () {
    $root = updateSafetyDirectory();
    $snapshotPath = $root->path('20260825-120000-invalid');
    File::ensureDirectoryExists($snapshotPath);
    File::put($snapshotPath . DIRECTORY_SEPARATOR . 'ROLLBACK.md', '# Incomplete');

    $service = app(UpdateSnapshotService::class);

    expect($service->fromPath($snapshotPath))->toBeNull()
        ->and($service->latest($root->path()))->toBeNull();
});
