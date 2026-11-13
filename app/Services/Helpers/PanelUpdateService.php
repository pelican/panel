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
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

/**
 * Updates a bare-metal install to a newer release in place.
 *
 * This version of the panel only does what is safe while its own files are still in place: the preflight checks,
 * downloading the release, verifying its checksum and extracting it into a staging directory. It then hands off
 * to the `update.sh` that ships in the release being installed, which installs its dependencies, takes the backups,
 * swaps the release in, migrates and rolls back on failure. No PHP from the old release runs after the swap, and
 * the steps of an update always come from the release they install.
 *
 * Both halves run under one `flock` on update.lock, held by the shell that starts them.
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

    /** Set by the shell that holds update.lock, the release preparation refuses to run without it. */
    public const LOCKED_ENV = 'PANEL_UPDATE_LOCKED';

    private const MIN_FREE_BYTES = 1_000_000_000;

    private const MAX_ARCHIVE_BYTES = 200 * 1024 * 1024;

    private const LOG_LINES = 100;

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
            // The updater relies on bash, flock, tar and chown.
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
            $binaries = ['php' => $this->phpCli(), 'composer' => $this->binary('composer'), 'tar' => $this->binary('tar'), 'bash' => $this->binary('bash'), 'flock' => $this->binary('flock')];
            foreach ($binaries as $name => $path) {
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
            $preflight = $this->preflight($target);
            throw_if(count($preflight['errors']) > 0, new Exception($preflight['errors'][0] ?? ''));
            throw_if(count($preflight['plugins']) > 0 && !$forcePlugins, new Exception(trans('admin/update.preflight.plugins', ['plugins' => implode(', ', $preflight['plugins'])])));
            throw_if($preflight['needs_backup_confirmation'] && !$skipDatabaseBackup, new Exception(trans('admin/update.preflight.no_dump_tool')));

            $secret = Str::random(40);
            $this->queue($target, $secret, $forcePlugins, $skipDatabaseBackup, $user->id);
            $this->start();

            return $secret;
        } finally {
            flock($launchLock, LOCK_UN);
            fclose($launchLock);
        }
    }

    /**
     * Records an update for the process started by start() to pick up with pullRequest().
     */
    public function queue(string $target, string $secret, bool $forcePlugins, bool $skipDatabaseBackup, ?int $userId): void
    {
        $this->writeRequest([
            'target' => $target,
            'secret' => $secret,
            'user_id' => $userId,
            'force_plugins' => $forcePlugins,
            'skip_db_backup' => $skipDatabaseBackup,
        ]);

        $this->writeStatus([
            'state' => self::STATE_QUEUED,
            'step' => null,
            'from' => $this->versionService->currentComparableVersion(),
            'to' => $target,
            'user_id' => $userId,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'message' => null,
            'log' => [],
        ], replace: true);
    }

    /**
     * The command that runs a queued update: `flock` holds update.lock for its whole run, this release prepares the
     * new one, then the shell execs the new release's update.sh, so no PHP from this release is left running.
     *
     * @return string[]
     */
    public function updateCommand(): array
    {
        $php = (string) $this->phpCli();
        $prepare = implode(' ', array_map('escapeshellarg', [$php, base_path('artisan'), 'p:update', '--from-request', '--no-interaction']));
        $install = implode(' ', array_map('escapeshellarg', ['bash', $this->releasePath('update.sh'), $php, $this->statePath('handoff.json')]));

        return [(string) $this->binary('flock'), '-n', $this->statePath('update.lock'), 'sh', '-c', "$prepare && exec $install"];
    }

    /** @return array<string, string> */
    public function updateEnv(): array
    {
        return [...$this->processEnv(), self::LOCKED_ENV => '1'];
    }

    /**
     * Starts the queued update in the background, logging to storage/logs/panel-update.log.
     *
     * @throws Exception
     */
    public function start(): void
    {
        $prefix = array_values(array_filter([$this->binary('setsid'), $this->binary('nohup')]));
        $shell = implode(' ', array_map('escapeshellarg', [...$prefix, ...$this->updateCommand()]))
            . ' >> ' . escapeshellarg(storage_path('logs/panel-update.log')) . ' 2>&1 < /dev/null &';

        $result = Process::path(base_path())->env($this->updateEnv())->run(['sh', '-c', $shell]);

        if ($result->failed()) {
            $reason = trim($result->errorOutput() ?: $result->output()) ?: 'exit code ' . $result->exitCode();
            $this->writeStatus(['state' => self::STATE_FAILED, 'message' => $reason, 'finished_at' => now()->toIso8601String()]);
            @unlink($this->statePath('request.json'));

            throw new Exception(trans('admin/update.notifications.launch_failed', ['reason' => $reason]));
        }
    }

    /**
     * Reads and removes the request written by queue().
     *
     * @return null|array{target: string, secret: string, user_id: ?int, force_plugins: bool, skip_db_backup: bool}
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
     * Prepares the release for its update.sh: preflight, download, checksum and extraction, then the handoff file
     * with what the script needs to know about this install. Nothing live is touched here.
     *
     * @param  array{target: string, secret: string, user_id: ?int, force_plugins: bool, skip_db_backup: bool}  $request
     * @param  Closure(string): void  $output
     */
    public function prepare(array $request, Closure $output): bool
    {
        $this->output = $output;
        $this->secret = $request['secret'];
        $target = $request['target'];

        if (getenv(self::LOCKED_ENV) !== '1') {
            $this->log('Updates must be started with `php artisan p:update` or from the admin dashboard.');

            return false;
        }

        $from = $this->versionService->currentComparableVersion() ?? 'canary';
        $staging = $this->workPath('staging');

        $this->writeStatus([
            'state' => self::STATE_RUNNING,
            'from' => $from,
            'to' => $target,
            'user_id' => $request['user_id'],
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'message' => null,
            'log' => [],
        ], replace: true);

        try {
            $this->step('preflight');
            $preflight = $this->preflight($target, holdsLock: true);
            throw_if(count($preflight['errors']) > 0, new Exception(implode(PHP_EOL, $preflight['errors'])));
            throw_if(count($preflight['plugins']) > 0 && !$request['force_plugins'], new Exception(trans('admin/update.preflight.plugins', ['plugins' => implode(', ', $preflight['plugins'])])));
            throw_if($preflight['needs_backup_confirmation'] && !$request['skip_db_backup'], new Exception(trans('admin/update.preflight.no_dump_tool')));
            foreach ($preflight['warnings'] as $warning) {
                $this->log("Warning: $warning");
            }

            $this->step('download');
            $archive = $this->download($target, $staging);
            $this->extract($archive, $this->releasePath(), $target);

            $this->writeHandoff($request, $from);
            $this->log('Handing off to the update script of ' . $target . '.');

            return true;
        } catch (Throwable $exception) {
            $this->log('Update failed: ' . $exception->getMessage());
            File::deleteDirectory($staging);

            $this->writeStatus([
                'state' => self::STATE_FAILED,
                'message' => $this->redact($exception->getMessage()),
                'finished_at' => now()->toIso8601String(),
            ]);

            return false;
        }
    }

    /**
     * Jobs a worker is processing right now. Drivers that can't tell, like sync, report none. update.sh asks the
     * live install for this through `p:update --running-jobs` before it backs up the database.
     *
     * @throws Throwable when the queue backend can't be inspected
     */
    public function runningQueueJobs(): int
    {
        $name = config('queue.default');
        $config = config("queue.connections.$name", []);

        return match ($config['driver'] ?? null) {
            // A reservation older than retry_after belongs to a worker that died, nobody is running that job.
            'database' => DB::connection($config['connection'] ?? null)->table($config['table'] ?? 'jobs')
                ->where('reserved_at', '>', time() - (int) ($config['retry_after'] ?? 90))
                ->count(),
            // The reserved set is scored by when the reservation expires.
            'redis' => (int) Redis::connection($config['connection'] ?? null)->zcount('queues:' . ($config['queue'] ?? 'default') . ':reserved', (string) time(), '+inf'),
            default => 0,
        };
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

    /** @throws Exception */
    private function extract(string $archive, string $release, string $target): void
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
        throw_unless(is_file(join_paths($release, 'update.sh')), new Exception("Version $target can't be installed from the dashboard, it has no update.sh."));

        $this->checkPlatform($release, $target);
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
     * What update.sh needs to know about this install. Read once and deleted by the script, it holds the database
     * password and the maintenance secret.
     *
     * @param  array{target: string, secret: string, user_id: ?int, force_plugins: bool, skip_db_backup: bool}  $request
     */
    private function writeHandoff(array $request, string $from): void
    {
        $config = $this->databaseConfig();
        $tools = $this->databaseTools();

        $handoff = [
            'version' => 1,
            'target' => $request['target'],
            'from' => $from,
            'secret' => $request['secret'],
            'skip_db_backup' => (bool) $request['skip_db_backup'],
            'base' => base_path(),
            'state' => $this->statePath(),
            'staging' => $this->workPath('staging'),
            'backups' => $this->workPath('backups'),
            'composer' => $this->binary('composer'),
            'yarn' => $this->binary('yarn'),
            'plugin_packages' => collect($this->pluginService->pluginComposerPackages())->map(fn ($version, $package) => "$package:$version")->values()->all(),
            'build_plugin_assets' => $this->pluginsNeedAssetBuild(),
            'config_cached' => file_exists(base_path('bootstrap/cache/config.php')),
            'database' => [
                'driver' => (string) ($config['driver'] ?? ''),
                'database' => (string) ($config['database'] ?? ''),
                'host' => (string) ($config['host'] ?? ''),
                'port' => (string) ($config['port'] ?? ''),
                'username' => (string) ($config['username'] ?? ''),
                'password' => (string) ($config['password'] ?? ''),
                'socket' => (string) ($config['unix_socket'] ?? ''),
                'dump' => is_array($tools) ? $tools['dump'] : '',
                'client' => is_array($tools) ? $tools['client'] : '',
            ],
        ];

        $path = $this->statePath('handoff.json');
        file_put_contents($path, json_encode($handoff, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        @chmod($path, 0600);
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
     * @param  string[]  $command
     *
     * @throws Exception
     */
    private function runProcess(array $command, ?string $path = null, int $timeout = 600, bool $quiet = false): ProcessResult
    {
        $label = implode(' ', array_slice($command, 0, 3));
        $this->log('$ ' . $label);

        $result = Process::path($path ?? base_path())
            ->env($this->processEnv())
            ->timeout($timeout)
            ->run($command, function (string $type, string $buffer) use ($quiet) {
                if (!$quiet) {
                    ($this->output)($this->redact($buffer));
                }
            });

        if ($result->failed()) {
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

    private function releasePath(string ...$paths): string
    {
        return join_paths($this->workPath('staging'), 'release', ...$paths);
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
