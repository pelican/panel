<?php

namespace App\Services\Helpers;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\User;
use Closure;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

/**
 * Updates a bare-metal install to a newer release in place.
 *
 * The release is downloaded, verified and fully prepared (composer, plugin packages, plugin assets) in a
 * staging directory while the panel keeps running. Only then does the panel go into maintenance mode, the
 * database gets backed up and every top-level release entry is swapped in with a rename, keeping the old one
 * as the code backup. Everything after the swap runs as a fresh `artisan` subprocess so it loads the new code,
 * and any failure from that point on puts the old code and database back.
 *
 * After the swap this process must not autoload anything new, since the files it would load now belong to the
 * new release. That is why the steps after it only use processes, plain files and already loaded classes.
 */
class PanelUpdateService
{
    public const STEPS = ['preflight', 'download', 'dependencies', 'maintenance', 'backup', 'swap', 'migrate', 'finish'];

    public const STATE_QUEUED = 'queued';

    public const STATE_RUNNING = 'running';

    public const STATE_SUCCEEDED = 'succeeded';

    /** The update failed and the panel is back on the version it started from. */
    public const STATE_FAILED = 'failed';

    /** The update failed and could not be fully undone, the panel stays in maintenance mode. */
    public const STATE_ROLLBACK_FAILED = 'rollback_failed';

    private const MIN_FREE_BYTES = 1_000_000_000;

    private const MAX_ARCHIVE_BYTES = 200 * 1024 * 1024;

    private const KEEP_BACKUPS = 3;

    private const LOG_LINES = 100;

    /** Top-level release entries that hold user data and are never replaced. */
    private const PRESERVED = ['storage', 'plugins', '.env'];

    /** Release directories whose extra live entries (sqlite database, storage symlink, custom files) are kept. */
    private const CARRY_OVER = ['public', 'database'];

    /** Release directories that get replaced, so they must be writable before anything starts. */
    private const REPLACED = ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'vendor'];

    /** PHP-FPM usually runs without a PATH, so binaries are looked up here as well. */
    private const BIN_DIRS = ['/usr/local/bin', '/usr/bin', '/bin', '/usr/local/sbin', '/usr/sbin', '/opt/homebrew/bin'];

    /** @var Closure(string): void */
    private Closure $output;

    private ?string $secret = null;

    public function __construct(
        private readonly SoftwareVersionService $versionService,
        private readonly PluginService $pluginService,
    ) {
        $this->output = fn (string $line) => null;
    }

    /**
     * Checks that can run before anything is downloaded. Errors block the update, plugins that would become
     * incompatible and a missing database dump tool need the admin's confirmation.
     *
     * @return array{errors: string[], warnings: string[], plugins: string[], needs_backup_confirmation: bool}
     */
    public function preflight(?string $target, bool $holdsLock = false): array
    {
        $errors = [];
        $warnings = [];
        $current = $this->versionService->currentComparableVersion();

        if (PHP_OS_FAMILY === 'Windows') {
            // The updater relies on sh, setsid, tar and chown.
            $errors[] = trans('admin/update.preflight.windows');
        } elseif (is_running_in_docker()) {
            $errors[] = trans('admin/update.preflight.docker');
        } elseif ($current === null) {
            $errors[] = trans('admin/update.preflight.canary');
        } elseif ($target === null) {
            $errors[] = trans('admin/update.preflight.no_target');
        } elseif (!version_compare($target, $current, '>')) {
            $errors[] = trans('admin/update.preflight.not_newer', ['current' => $current, 'target' => $target]);
        }

        if (!$holdsLock && $this->isBusy()) {
            $errors[] = trans('admin/update.preflight.running');
        }

        if (!function_exists('proc_open')) {
            $errors[] = trans('admin/update.preflight.proc_open');
        } else {
            foreach (['php' => $this->phpCli(), 'composer' => $this->binary('composer'), 'tar' => $this->binary('tar')] as $name => $path) {
                if ($path === null) {
                    $errors[] = trans('admin/update.preflight.binary', ['binary' => $name]);
                }
            }
        }

        foreach (['', ...self::REPLACED] as $entry) {
            $path = base_path($entry);
            if (file_exists($path) && !is_writable($path)) {
                $errors[] = trans('admin/update.preflight.not_writable', ['path' => $path]);
            }
        }

        $free = @disk_free_space($this->workPath());
        if ($free !== false && $free < self::MIN_FREE_BYTES) {
            $errors[] = trans('admin/update.preflight.disk_space', ['required' => convert_bytes_to_readable(self::MIN_FREE_BYTES)]);
        }

        if ($this->pluginsNeedAssetBuild() && $this->binary('yarn') === null) {
            $warnings[] = trans('admin/update.preflight.no_yarn');
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'plugins' => $target === null ? [] : $this->incompatiblePlugins($target),
            'needs_backup_confirmation' => $this->databaseTools() === false,
        ];
    }

    /**
     * Names of the enabled plugins that the target version would mark as incompatible.
     *
     * @return string[]
     */
    public function incompatiblePlugins(string $target): array
    {
        try {
            Plugin::refreshRows();

            return Plugin::all()
                ->filter(fn (Plugin $plugin) => $plugin->status === PluginStatus::Enabled && !$plugin->isCompatible($target))
                ->map(fn (Plugin $plugin) => $plugin->name)
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * Starts the update in a detached process so it outlives the request and returns the maintenance mode bypass
     * secret for the admin who started it.
     *
     * @throws Exception
     */
    public function launch(User $user, string $target, bool $forcePlugins = false, bool $skipDatabaseBackup = false): string
    {
        throw_unless($user->isRootAdmin(), new AuthorizationException(trans('admin/update.notifications.unauthorized')));

        // Two admins confirming at once must not both pass preflight, the second one has to see the first as queued.
        File::ensureDirectoryExists($this->statePath());
        $launchLock = fopen($this->statePath('launch.lock'), 'c');
        throw_if($launchLock === false || !flock($launchLock, LOCK_EX | LOCK_NB), new Exception(trans('admin/update.preflight.running')));

        try {
            return $this->launchLocked($user, $target, $forcePlugins, $skipDatabaseBackup);
        } finally {
            flock($launchLock, LOCK_UN);
            fclose($launchLock);
        }
    }

    /** @throws Exception */
    private function launchLocked(User $user, string $target, bool $forcePlugins, bool $skipDatabaseBackup): string
    {
        $preflight = $this->preflight($target);
        throw_if(count($preflight['errors']) > 0, new Exception($preflight['errors'][0] ?? ''));
        throw_if(count($preflight['plugins']) > 0 && !$forcePlugins, new Exception(trans('admin/update.preflight.plugins', ['plugins' => implode(', ', $preflight['plugins'])])));
        throw_if($preflight['needs_backup_confirmation'] && !$skipDatabaseBackup, new Exception(trans('admin/update.preflight.no_dump_tool')));

        $secret = Str::random(40);

        $this->writeRequest([
            'target' => $target,
            'secret' => $secret,
            'user_id' => $user->id,
            'force_plugins' => $forcePlugins,
            'skip_db_backup' => $skipDatabaseBackup,
        ]);

        $this->writeStatus([
            'state' => self::STATE_QUEUED,
            'step' => null,
            'from' => $this->versionService->currentComparableVersion(),
            'to' => $target,
            'user_id' => $user->id,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'message' => null,
            'log' => [],
        ], replace: true);

        $command = [$this->phpCli(), base_path('artisan'), 'p:update', '--from-request', '--no-interaction'];
        $prefix = array_values(array_filter([$this->binary('setsid'), $this->binary('nohup')]));
        $shell = implode(' ', array_map('escapeshellarg', [...$prefix, ...$command]))
            . ' >> ' . escapeshellarg(storage_path('logs/panel-update.log')) . ' 2>&1 < /dev/null &';

        $result = Process::path(base_path())->env($this->processEnv())->run(['sh', '-c', $shell]);

        if ($result->failed()) {
            $reason = trim($result->errorOutput() ?: $result->output()) ?: 'exit code ' . $result->exitCode();
            $this->writeStatus(['state' => self::STATE_FAILED, 'message' => $reason, 'finished_at' => now()->toIso8601String()]);
            @unlink($this->statePath('request.json'));

            throw new Exception(trans('admin/update.notifications.launch_failed', ['reason' => $reason]));
        }

        return $secret;
    }

    /**
     * Reads and removes the request written by launch().
     *
     * @return null|array{target: string, secret: string, user_id: int, force_plugins: bool, skip_db_backup: bool}
     */
    public function pullRequest(): ?array
    {
        $path = $this->statePath('request.json');
        if (!is_file($path)) {
            return null;
        }

        $request = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        return is_array($request) && is_string($request['target'] ?? null) ? $request : null;
    }

    /**
     * Runs the whole update. Returns whether the panel ended up on the target version.
     *
     * @param  Closure(string): void  $output
     */
    public function run(string $target, ?string $secret, bool $forcePlugins, bool $skipDatabaseBackup, ?int $userId, Closure $output): bool
    {
        $this->output = $output;
        $this->secret = $secret ?? Str::random(40);

        File::ensureDirectoryExists($this->statePath());
        $lock = fopen($this->statePath('update.lock'), 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            $this->log('Another update is already running.');

            return false;
        }

        // Whoever owns the install, so a run as root can hand back what it creates.
        $owner = @fileowner(base_path('public'));
        $group = @filegroup(base_path('public'));

        $from = $this->versionService->currentComparableVersion() ?? 'canary';
        $staging = $this->workPath('staging');
        $release = join_paths($staging, 'release');
        $backup = $this->workPath('backups', $from . '_' . now()->format('Ymd_His'));
        $codeBackup = join_paths($backup, 'code');

        $isDown = false;
        $migrating = false;
        $databaseBackup = null;
        /** @var array<string, bool> $swapped release entry => whether a live entry was moved aside for it */
        $swapped = [];
        /** @var list<array{string, string}> $carried [directory, name] moved from the live into the release directory */
        $carried = [];

        $this->writeStatus([
            'state' => self::STATE_RUNNING,
            'from' => $from,
            'to' => $target,
            'user_id' => $userId,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'message' => null,
            'log' => [],
        ], replace: true);

        try {
            $this->step('preflight');
            $preflight = $this->preflight($target, holdsLock: true);
            throw_if(count($preflight['errors']) > 0, new Exception(implode(PHP_EOL, $preflight['errors'])));
            throw_if(count($preflight['plugins']) > 0 && !$forcePlugins, new Exception(trans('admin/update.preflight.plugins', ['plugins' => implode(', ', $preflight['plugins'])])));
            throw_if($preflight['needs_backup_confirmation'] && !$skipDatabaseBackup, new Exception(trans('admin/update.preflight.no_dump_tool')));
            foreach ($preflight['warnings'] as $warning) {
                $this->log("Warning: $warning");
            }

            $this->step('download');
            $archive = $this->download($target, $staging);

            $this->step('dependencies');
            $this->stage($archive, $release, $target);

            // Resolve everything that is needed after the swap while the old files are still in place.
            $php = $this->phpCli();
            $configCached = file_exists(base_path('bootstrap/cache/config.php'));

            $this->step('maintenance');
            $this->artisan(['down', '--secret=' . $this->secret, '--render=errors::updating', '--retry=15', '--refresh=15']);
            $isDown = true;
            $this->artisan(['queue:restart'], throw: false);

            $this->step('backup');
            $databaseBackup = $this->backupDatabase($backup, $skipDatabaseBackup);

            $this->step('swap');
            $this->swap($release, $codeBackup, $swapped, $carried);

            $this->step('migrate');
            $this->artisan(['package:discover'], php: $php);
            $this->artisan(['filament:upgrade'], php: $php, throw: false);
            $this->artisan(['optimize:clear'], php: $php);
            $migrating = true;
            $this->artisan(['migrate', '--force', '--seed'], timeout: 1800, php: $php);

            $this->step('finish');
            if ($configCached) {
                $this->artisan(['optimize'], php: $php, throw: false);
            }
            $this->fixOwnership(array_keys($swapped), $owner, $group);
            $this->artisan(['queue:restart'], php: $php, throw: false);
            $this->artisan(['up'], php: $php);
            $isDown = false;

            File::deleteDirectory($staging);
            $this->pruneBackups();

            $this->log("Updated from $from to $target.");
            $this->writeStatus(['state' => self::STATE_SUCCEEDED, 'step' => null, 'finished_at' => now()->toIso8601String()]);

            return true;
        } catch (Throwable $exception) {
            $this->log('Update failed: ' . $exception->getMessage());

            $restored = true;
            if ($isDown || count($swapped) > 0) {
                $this->log('Rolling back...');
                $restored = $this->rollback($swapped, $carried, $release, $codeBackup, $migrating ? $databaseBackup : null, $migrating);
            }

            // Staging can hold custom public/ and database/ files that a failed rollback didn't put back.
            if ($restored) {
                File::deleteDirectory($staging);
            }

            $this->writeStatus([
                'state' => $restored ? self::STATE_FAILED : self::STATE_ROLLBACK_FAILED,
                'message' => $this->redact($exception->getMessage()),
                'backup' => $restored ? null : $backup,
                'staging' => $restored ? null : $staging,
                'finished_at' => now()->toIso8601String(),
            ]);

            return false;
        } finally {
            // Run as root, everything this wrote under storage is root's, and the web user couldn't launch,
            // dismiss or log anymore. The state directory goes last since chowning logs to its status file.
            $workPath = $this->workPath();
            $this->fixOwnership(array_values(array_unique(array_filter([
                'storage/logs',
                'storage/framework',
                str_starts_with($workPath, $this->statePath()) ? null : Str::after($workPath, base_path() . DIRECTORY_SEPARATOR),
                Str::after($this->statePath(), base_path() . DIRECTORY_SEPARATOR),
            ]))), $owner, $group);

            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return null|array<string, mixed> */
    public function status(): ?array
    {
        $status = $this->readStatus();
        $state = $status['state'] ?? null;

        if (!in_array($state, [self::STATE_QUEUED, self::STATE_RUNNING], true) || $this->lockHeld()) {
            return $status;
        }

        // The update holds the lock for its whole run, so running without it means the process died. A queued update
        // gets a moment to start, the launch command can't tell whether the detached process came up.
        if ($state === self::STATE_QUEUED && strtotime($status['updated_at'] ?? '') > now()->subMinutes(2)->getTimestamp()) {
            return $status;
        }

        // Before maintenance mode nothing live was touched. After it, the panel may be down or half swapped.
        $touchedLive = $state === self::STATE_RUNNING && !in_array($status['step'] ?? null, ['preflight', 'download', 'dependencies'], true);

        return [
            ...$status,
            'state' => $touchedLive ? self::STATE_ROLLBACK_FAILED : self::STATE_FAILED,
            'message' => trans('admin/update.progress.stopped'),
            'finished_at' => $status['updated_at'] ?? null,
        ];
    }

    /** @return null|array<string, mixed> */
    private function readStatus(): ?array
    {
        $path = $this->statePath('status.json');
        if (!is_file($path)) {
            return null;
        }

        $status = json_decode((string) @file_get_contents($path), true);

        return is_array($status) ? $status : null;
    }

    private function lockHeld(): bool
    {
        $lock = @fopen($this->statePath('update.lock'), 'c');
        if ($lock === false) {
            return false;
        }

        $free = flock($lock, LOCK_EX | LOCK_NB);
        if ($free) {
            flock($lock, LOCK_UN);
        }
        fclose($lock);

        return !$free;
    }

    public function dismissStatus(): void
    {
        @unlink($this->statePath('status.json'));
    }

    public function isBusy(): bool
    {
        if ($this->lockHeld()) {
            return true;
        }

        // Launched but the process hasn't taken the lock yet.
        $status = $this->readStatus();

        return ($status['state'] ?? null) === self::STATE_QUEUED
            && strtotime($status['updated_at'] ?? '') > now()->subMinutes(2)->getTimestamp();
    }

    /** @throws Exception */
    private function download(string $version, string $staging): string
    {
        File::deleteDirectory($staging);
        File::ensureDirectoryExists($staging);

        $assets = $this->versionService->panelReleaseAssets($version);
        throw_unless(isset($assets['panel.tar.gz'], $assets['checksum.txt']), new Exception("Release $version is missing panel.tar.gz or checksum.txt."));

        $checksum = trim(Http::timeout(30)->connectTimeout(10)->get($assets['checksum.txt'])->throw()->body());
        throw_unless(preg_match('/^([a-f0-9]{64})\b/i', $checksum, $matches), new Exception('The release checksum file is invalid.'));

        $archive = join_paths($staging, 'panel.tar.gz');
        $this->log('Downloading ' . $assets['panel.tar.gz']);
        Http::timeout(600)->connectTimeout(10)->sink($archive)->get($assets['panel.tar.gz'])->throw();

        throw_if(filesize($archive) > self::MAX_ARCHIVE_BYTES, new Exception('The release archive is unexpectedly large.'));
        throw_unless(hash_equals(strtolower($matches[1]), (string) hash_file('sha256', $archive)), new Exception('The release archive does not match its published checksum.'));
        $this->log('Checksum verified.');

        return $archive;
    }

    /**
     * Extracts the release and installs everything it needs, so problems show up before any downtime.
     *
     * @throws Exception
     */
    private function stage(string $archive, string $release, string $target): void
    {
        $tar = (string) $this->binary('tar');
        File::ensureDirectoryExists($release);

        $entries = preg_split('/\R/', trim($this->runProcess([$tar, '-tzf', $archive], timeout: 120, quiet: true)->output())) ?: [];
        foreach ($entries as $entry) {
            throw_if(str_starts_with($entry, '/') || in_array('..', explode('/', $entry), true), new Exception("The release archive contains an unsafe path: $entry"));
        }

        $this->runProcess([$tar, '-xzf', $archive, '-C', $release], timeout: 300);
        @unlink($archive);

        $config = @file_get_contents(join_paths($release, 'config/app.php'));
        throw_unless(file_exists(join_paths($release, 'artisan')) && $config !== false, new Exception('The release archive is not a panel release.'));
        throw_unless(preg_match("/'version'\s*=>\s*'" . preg_quote($target, '/') . "'/", $config), new Exception("The release archive is not version $target."));

        $this->checkPlatform($release, $target);

        $composer = (string) $this->binary('composer');
        $this->runProcess([$composer, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress', '--no-scripts'], $release, 900);

        // The archive may ship caches built with dev packages, which would reference providers that aren't installed.
        foreach (glob(join_paths($release, 'bootstrap/cache') . '/*.php') ?: [] as $cache) {
            @unlink($cache);
        }

        $packages = collect($this->pluginService->pluginComposerPackages())->map(fn ($version, $package) => "$package:$version")->values()->all();
        if (count($packages) > 0) {
            $this->log('Installing plugin composer packages: ' . implode(', ', $packages));
            $this->runProcess([$composer, 'require', '--update-no-dev', '--no-interaction', '--no-progress', '--no-scripts', ...$packages], $release, 900);
        }

        $yarn = $this->binary('yarn');
        if ($yarn !== null && $this->pluginsNeedAssetBuild()) {
            $this->log('Building frontend assets for plugins.');

            // vite picks up plugin assets from plugins/*, so point the release at the live plugins for the build.
            $plugins = join_paths($release, 'plugins');
            File::deleteDirectory($plugins);
            throw_unless(@symlink(base_path('plugins'), $plugins), new Exception('Could not link the plugins into the release.'));

            try {
                $this->runProcess([$yarn, 'install'], $release, 900);
                $this->runProcess([$yarn, 'build'], $release, 900);
            } finally {
                @unlink($plugins);
                File::deleteDirectory(join_paths($release, 'node_modules'));
            }
        }
    }

    /** @throws Exception */
    private function checkPlatform(string $release, string $target): void
    {
        $require = json_decode((string) @file_get_contents(join_paths($release, 'composer.json')), true)['require'] ?? [];

        $constraint = $require['php'] ?? null;
        if (is_string($constraint) && !$this->phpSatisfies($constraint)) {
            throw new Exception("Version $target requires PHP $constraint, but this server runs PHP " . PHP_VERSION . '.');
        }

        $missing = collect(array_keys($require))
            ->filter(fn (string $package) => str_starts_with($package, 'ext-') && !extension_loaded(substr($package, 4)))
            ->values()->all();
        throw_if(count($missing) > 0, new Exception("Version $target requires missing PHP extensions: " . implode(', ', $missing)));
    }

    /**
     * Understands the `^x.y`, `>=x.y` and `*` constraints (joined with `||`) the panel uses. Anything else is left to composer.
     */
    private function phpSatisfies(string $constraint): bool
    {
        foreach (preg_split('/\s*\|\|?\s*/', trim($constraint)) ?: [] as $alternative) {
            if ($alternative === '*') {
                return true;
            }

            if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $alternative, $matches)) {
                $minimum = $matches[1] . '.' . ($matches[2] ?? '0') . '.' . ($matches[3] ?? '0');
                $upper = ((int) $matches[1] + 1) . '.0.0';

                if (version_compare(PHP_VERSION, $minimum, '>=') && version_compare(PHP_VERSION, $upper, '<')) {
                    return true;
                }

                continue;
            }

            if (preg_match('/^>=\s*([\d.]+)$/', $alternative, $matches)) {
                if (version_compare(PHP_VERSION, $matches[1], '>=')) {
                    return true;
                }

                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @return null|array{driver: string, file: string}
     *
     * @throws Exception
     */
    private function backupDatabase(string $backup, bool $skip): ?array
    {
        File::ensureDirectoryExists($backup);
        $config = $this->databaseConfig();
        $driver = $config['driver'] ?? null;

        if ($driver === 'sqlite') {
            $file = join_paths($backup, 'database.sqlite');
            throw_unless(is_file($config['database']), new Exception('Could not back up the sqlite database.'));

            // A plain copy misses committed transactions still in the -wal file, VACUUM INTO writes a consistent snapshot.
            @unlink($file);
            DB::connection()->statement('VACUUM INTO ?', [$file]);
            throw_unless(is_file($file) && filesize($file) > 0, new Exception('Could not back up the sqlite database.'));
            $this->log("Database backed up to $file");

            return ['driver' => $driver, 'file' => $file];
        }

        $tools = $this->databaseTools();
        if ($tools === false || $tools === null) {
            throw_unless($skip, new Exception(trans('admin/update.preflight.no_dump_tool')));
            $this->log('Skipping the database backup, no dump tool is available.');

            return null;
        }

        $file = join_paths($backup, 'database.sql');
        $command = match ($driver) {
            'pgsql' => [$tools['dump'], '--no-owner', '--no-privileges', '--host=' . $config['host'], '--port=' . $config['port'], '--username=' . $config['username'], '--file=' . $file, $config['database']],
            default => [$tools['dump'], '--single-transaction', '--no-tablespaces', ...$this->mysqlConnectionArgs($config), '--result-file=' . $file, $config['database']],
        };

        $this->runProcess($command, timeout: 1800, env: $this->databaseEnv($config), quiet: true);
        throw_unless(filesize($file) > 0, new Exception('The database backup is empty.'));
        $this->log("Database backed up to $file");

        return ['driver' => $driver, 'file' => $file];
    }

    /** @param array{driver: string, file: string} $backup */
    private function restoreDatabase(array $backup): void
    {
        $config = $this->databaseConfig();

        if ($backup['driver'] === 'sqlite') {
            @unlink($config['database'] . '-wal');
            @unlink($config['database'] . '-shm');
            throw_unless(copy($backup['file'], $config['database']), new Exception('Could not restore the sqlite database.'));

            return;
        }

        $tools = $this->databaseTools();
        throw_unless(is_array($tools), new Exception('No database client is available to restore the backup.'));

        // Tables created by the new migrations aren't in the dump, so start from an empty database.
        $this->artisan(['db:wipe', '--force']);

        $command = match ($backup['driver']) {
            'pgsql' => [$tools['client'], '-v', 'ON_ERROR_STOP=1', '--host=' . $config['host'], '--port=' . $config['port'], '--username=' . $config['username'], '--dbname=' . $config['database']],
            default => [$tools['client'], ...$this->mysqlConnectionArgs($config), '--database=' . $config['database']],
        };

        $input = fopen($backup['file'], 'r');
        throw_if($input === false, new Exception('Could not read the database backup.'));

        try {
            $this->runProcess($command, timeout: 1800, env: $this->databaseEnv($config), quiet: true, input: $input);
        } finally {
            fclose($input);
        }
    }

    /**
     * Moves every release entry into place, keeping the live one in the code backup.
     *
     * @param  array<string, bool>  $swapped
     * @param  list<array{string, string}>  $carried
     *
     * @throws Exception
     */
    private function swap(string $release, string $codeBackup, array &$swapped, array &$carried): void
    {
        File::ensureDirectoryExists($codeBackup);

        foreach (self::CARRY_OVER as $directory) {
            $live = base_path($directory);
            $staged = join_paths($release, $directory);
            if (!is_dir($live) || !is_dir($staged)) {
                continue;
            }

            foreach (array_diff(scandir($live) ?: [], ['.', '..']) as $name) {
                if (file_exists(join_paths($staged, $name)) || is_link(join_paths($staged, $name))) {
                    continue;
                }

                $this->move(join_paths($live, $name), join_paths($staged, $name));
                $carried[] = [$directory, $name];
            }
        }

        foreach (array_diff(scandir($release) ?: [], ['.', '..', ...self::PRESERVED]) as $name) {
            $live = base_path($name);
            $hadLive = file_exists($live) || is_link($live);

            if ($hadLive) {
                $this->move($live, join_paths($codeBackup, $name));
            }

            $swapped[$name] = $hadLive;
            $this->move(join_paths($release, $name), $live);
        }

        $this->log('Swapped in ' . count($swapped) . ' release entries.');
    }

    /**
     * @param  array<string, bool>  $swapped
     * @param  list<array{string, string}>  $carried
     * @param  null|array{driver: string, file: string}  $databaseBackup
     */
    private function rollback(array $swapped, array $carried, string $release, string $codeBackup, ?array $databaseBackup, bool $migrating): bool
    {
        try {
            $failed = $codeBackup . '-failed';
            File::ensureDirectoryExists($failed);

            foreach (array_reverse($swapped, true) as $name => $hadLive) {
                $live = base_path($name);
                if (file_exists($live) || is_link($live)) {
                    $this->move($live, join_paths($failed, $name));
                }

                if ($hadLive) {
                    $this->move(join_paths($codeBackup, $name), $live);
                }
            }

            foreach ($carried as [$directory, $name]) {
                foreach ([join_paths($failed, $directory, $name), join_paths($release, $directory, $name)] as $source) {
                    if (file_exists($source) || is_link($source)) {
                        $this->move($source, base_path(join_paths($directory, $name)));

                        break;
                    }
                }
            }

            File::deleteDirectory($failed);
            $this->log('Restored the previous code.');

            if ($migrating) {
                throw_if($databaseBackup === null, new Exception('There is no database backup to restore, the panel stays in maintenance mode.'));

                $this->restoreDatabase($databaseBackup);
                $this->log('Restored the database backup.');
            }

            $this->artisan(['optimize:clear']);
            $this->artisan(['queue:restart'], throw: false);
            $this->artisan(['up']);
            $this->log('Rolled back, the panel is back online.');

            return true;
        } catch (Throwable $exception) {
            $this->log('Rollback failed: ' . $exception->getMessage());
            $this->log('The panel is still in maintenance mode. Backups are in ' . dirname($codeBackup));

            return false;
        }
    }

    /** @param  string[]  $entries */
    private function fixOwnership(array $entries, int|false $owner, int|false $group): void
    {
        // Only root can create files for another user, everyone else already owns what they wrote.
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0 || $owner === false || $owner === 0) {
            return;
        }

        foreach ($entries as $entry) {
            $this->runProcess(['chown', '-R', $owner . ':' . ($group === false ? $owner : $group), base_path($entry)], throw: false, quiet: true);
        }
    }

    private function pruneBackups(): void
    {
        // Plain glob, this runs after the swap where autoloading a class could pick up the new release's version.
        $backups = glob($this->workPath('backups') . '/*', GLOB_ONLYDIR) ?: [];

        // Names are <version>_<Ymd_His>, sort by the timestamp since versions don't sort as strings (1.10 < 1.7).
        usort($backups, fn (string $a, string $b) => strcmp(substr(basename($a), -15), substr(basename($b), -15)));

        foreach (array_slice($backups, 0, max(0, count($backups) - self::KEEP_BACKUPS)) as $directory) {
            File::deleteDirectory($directory);
        }
    }

    /** @throws Exception */
    private function move(string $from, string $to): void
    {
        File::ensureDirectoryExists(dirname($to));
        throw_unless(@rename($from, $to), new Exception("Could not move $from to $to."));
    }

    private function pluginsNeedAssetBuild(): bool
    {
        foreach (['css', 'js'] as $type) {
            if (count(File::glob(base_path("plugins/*/resources/$type"))) > 0) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function databaseConfig(): array
    {
        // Resolve DB_URL like Laravel does, so the dump tools reach the same database as db:wipe and migrate.
        return (new ConfigurationUrlParser())->parseConfiguration(config('database.connections.' . config('database.default'), []));
    }

    /**
     * The dump and client binaries for the database, null for sqlite which only needs a file copy, false when missing.
     *
     * @return null|false|array{dump: string, client: string}
     */
    private function databaseTools(): null|false|array
    {
        $driver = $this->databaseConfig()['driver'] ?? null;
        if ($driver === 'sqlite') {
            return null;
        }

        [$dump, $client] = match ($driver) {
            'mysql', 'mariadb' => [$this->binary('mariadb-dump') ?? $this->binary('mysqldump'), $this->binary('mariadb') ?? $this->binary('mysql')],
            'pgsql' => [$this->binary('pg_dump'), $this->binary('psql')],
            default => [null, null],
        };

        return is_string($dump) && is_string($client) ? ['dump' => $dump, 'client' => $client] : false;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return string[]
     */
    private function mysqlConnectionArgs(array $config): array
    {
        if (filled($config['unix_socket'] ?? null)) {
            return ['--socket=' . $config['unix_socket'], '--user=' . $config['username']];
        }

        return ['--host=' . $config['host'], '--port=' . $config['port'], '--user=' . $config['username']];
    }

    /**
     * Passwords go through the environment so they never show up in the process list.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function databaseEnv(array $config): array
    {
        return ['MYSQL_PWD' => (string) ($config['password'] ?? ''), 'PGPASSWORD' => (string) ($config['password'] ?? '')];
    }

    /**
     * @param  string[]  $arguments
     *
     * @throws Exception
     */
    private function artisan(array $arguments, int $timeout = 600, bool $throw = true, ?string $php = null): ProcessResult
    {
        return $this->runProcess([$php ?? (string) $this->phpCli(), 'artisan', ...$arguments], timeout: $timeout, throw: $throw);
    }

    /**
     * @param  string[]  $command
     * @param  array<string, string>  $env
     *
     * @throws Exception
     */
    private function runProcess(array $command, ?string $path = null, int $timeout = 600, array $env = [], bool $throw = true, bool $quiet = false, mixed $input = null): ProcessResult
    {
        $label = implode(' ', array_slice($command, 0, 3));
        $this->log('$ ' . $label);

        $result = Process::path($path ?? base_path())
            ->env([...$this->processEnv(), ...$env])
            ->timeout($timeout)
            ->input($input)
            ->run($command, function (string $type, string $buffer) use ($quiet) {
                if (!$quiet) {
                    ($this->output)($this->redact($buffer));
                }
            });

        if ($throw && $result->failed()) {
            $details = Str::limit(trim($result->errorOutput() ?: $result->output()), 2000);

            throw new Exception($this->redact("`$label` failed: $details"));
        }

        return $result;
    }

    /** @return array<string, string> */
    private function processEnv(): array
    {
        $path = array_filter([...explode(PATH_SEPARATOR, (string) getenv('PATH')), ...self::BIN_DIRS]);
        $env = ['PATH' => implode(PATH_SEPARATOR, array_unique($path)), 'COMPOSER_NO_INTERACTION' => '1'];

        if (!getenv('HOME') && !getenv('COMPOSER_HOME')) {
            $env['COMPOSER_HOME'] = $this->workPath('composer');
        }

        return $env;
    }

    private function phpCli(): ?string
    {
        if (in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            return PHP_BINARY;
        }

        // Under FPM, PHP_BINARY is php-fpm. Prefer the CLI of the same version over whatever `php` points to.
        return $this->binary('php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION)
            ?? ((new PhpExecutableFinder())->find(false) ?: null)
            ?? $this->binary('php');
    }

    private function binary(string $name): ?string
    {
        return (new ExecutableFinder())->find($name, null, self::BIN_DIRS);
    }

    private function step(string $step): void
    {
        $this->log('==> ' . Str::headline($step));
        $this->writeStatus(['step' => $step]);
    }

    private function log(string $line): void
    {
        $line = $this->redact($line);
        ($this->output)($line . PHP_EOL);

        $status = $this->readStatus() ?? [];
        $log = [...($status['log'] ?? []), '[' . date('H:i:s') . '] ' . $line];
        $this->writeStatus(['log' => array_slice($log, -self::LOG_LINES)]);
    }

    private function redact(string $text): string
    {
        return $this->secret === null ? $text : str_replace($this->secret, '********', $text);
    }

    /** @param array<string, mixed> $request */
    private function writeRequest(array $request): void
    {
        $path = $this->statePath('request.json');
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, json_encode($request, JSON_THROW_ON_ERROR));
        @chmod($path, 0600);
    }

    /** @param array<string, mixed> $changes */
    private function writeStatus(array $changes, bool $replace = false): void
    {
        $path = $this->statePath('status.json');
        File::ensureDirectoryExists(dirname($path));

        $status = [...($replace ? [] : $this->readStatus() ?? []), ...$changes, 'updated_at' => date(DATE_ATOM)];

        // Write and rename so a poll never reads half a file.
        $temporary = $path . '.' . getmypid() . '.tmp';
        file_put_contents($temporary, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @rename($temporary, $path);
    }

    private function statePath(string ...$paths): string
    {
        return join_paths(storage_path('app/private/update'), ...$paths);
    }

    /**
     * Staging and backups must share a filesystem with the install, so every swap is a plain rename.
     */
    private function workPath(string ...$paths): string
    {
        $base = $this->statePath();
        File::ensureDirectoryExists($base);

        if ((@stat($base)['dev'] ?? null) !== (@stat(base_path())['dev'] ?? null)) {
            $base = base_path('.panel-update');
        }

        return join_paths($base, ...$paths);
    }
}
