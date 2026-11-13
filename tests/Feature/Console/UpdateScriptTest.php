<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/*
 * Runs the real update.sh against a fake install. artisan and composer are stand-ins, the swap, backups and
 * rollback are the script's own, done to real files and a real sqlite database.
 */

/**
 * The artisan of both the live install and the release, it records what it ran and acts out a migration.
 */
const FAKE_ARTISAN = <<<'PHP'
<?php
$root = getenv('FAKE_ROOT');
file_put_contents("$root/artisan.log", basename(__DIR__) . ': ' . implode(' ', array_slice($argv, 1)) . "\n", FILE_APPEND);

switch ($argv[1] ?? '') {
    case 'p:update':
        echo "0\n";
        break;
    case 'down':
        touch("$root/down");
        break;
    case 'up':
        @unlink("$root/down");
        break;
    case 'migrate':
        (new PDO('sqlite:' . __DIR__ . '/database/database.sqlite'))->exec("UPDATE things SET name = 'migrated'");
        // What the new release does when it boots and finds the plugin incompatible.
        file_put_contents(__DIR__ . '/plugins/example/plugin.json', '{"meta":{"status":"incompatible"}}');
        if (file_exists("$root/hang-migrate")) {
            touch("$root/migrating");
            sleep(2);
        }
        if (file_exists("$root/fail-migrate")) {
            fwrite(STDERR, "migration exploded\n");
            exit(1);
        }
        break;
}
PHP;

beforeEach(function () {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('update.sh only runs on Linux');
    }
});

function fakeInstall(): array
{
    $root = sys_get_temp_dir() . '/panel-update-script-' . Str::random(8);
    $base = "$root/panel";
    $release = "$base/storage/app/private/update/staging/release";

    $put = function (string $path, string $contents) {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    };

    // The live install, with user data mixed into release directories.
    $put("$base/artisan", FAKE_ARTISAN);
    $put("$base/app/Old.php", 'old');
    $put("$base/public/index.php", 'old index');
    $put("$base/public/custom-logo.png", 'user file');
    $put("$base/database/migrations/old.php", 'old migration');
    $put("$base/storage/app/user-upload.txt", 'upload');
    $put("$base/plugins/example/plugin.json", '{"meta":{"status":"enabled"}}');
    $put("$base/.env", 'APP_KEY=secret');

    $database = new PDO("sqlite:$base/database/database.sqlite");
    $database->exec('CREATE TABLE things (name TEXT)');
    $database->exec("INSERT INTO things VALUES ('before the update')");
    $database = null;

    // The staged release, as PanelUpdateService::prepare() leaves it.
    $put("$release/artisan", FAKE_ARTISAN);
    $put("$release/app/New.php", 'new');
    $put("$release/public/index.php", 'new index');
    $put("$release/database/migrations/new.php", 'new migration');
    $put("$release/vendor/autoload.php", 'new vendor');
    $put("$release/storage/.gitignore", '*');
    $put("$release/plugins/.gitignore", '*');
    File::copy(base_path('update.sh'), "$release/update.sh");

    $put("$root/bin/composer", "#!/bin/sh\necho \"composer \$*\" >> \"\$FAKE_ROOT/composer.log\"\n");
    chmod("$root/bin/composer", 0755);

    $state = "$base/storage/app/private/update";
    $put("$state/handoff.json", json_encode([
        'version' => 1,
        'target' => '1.1.0',
        'from' => '1.0.0',
        'secret' => 'maintenance-secret',
        'skip_db_backup' => false,
        'base' => $base,
        'state' => $state,
        'staging' => "$state/staging",
        'backups' => "$state/backups",
        'composer' => "$root/bin/composer",
        'yarn' => null,
        'plugin_packages' => [],
        'build_plugin_assets' => false,
        'config_cached' => false,
        'database' => ['driver' => 'sqlite', 'database' => "$base/database/database.sqlite", 'host' => '', 'port' => '', 'username' => '', 'password' => '', 'socket' => '', 'dump' => '', 'client' => ''],
    ]));
    $put("$state/status.json", json_encode(['state' => 'running', 'step' => 'download', 'log' => []]));

    return [$root, $base, $state];
}

function runUpdateScript(string $root, string $state): Process
{
    $process = new Process(
        ['bash', "$state/staging/release/update.sh", PHP_BINARY, "$state/handoff.json"],
        env: ['FAKE_ROOT' => $root],
        timeout: 120,
    );
    $process->run();

    return $process;
}

function thingName(string $base): string
{
    return (string) (new PDO("sqlite:$base/database/database.sqlite"))->query('SELECT name FROM things')->fetchColumn();
}

it('swaps the release in without losing user data', function () {
    [$root, $base, $state] = fakeInstall();

    try {
        $process = runUpdateScript($root, $state);
        expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput());

        expect(File::get("$base/app/New.php"))->toBe('new')
            ->and(File::exists("$base/app/Old.php"))->toBeFalse()
            ->and(File::get("$base/public/index.php"))->toBe('new index')
            ->and(File::get("$base/public/custom-logo.png"))->toBe('user file')
            ->and(File::exists("$base/database/migrations/old.php"))->toBeFalse()
            ->and(File::get("$base/vendor/autoload.php"))->toBe('new vendor')
            ->and(File::get("$base/storage/app/user-upload.txt"))->toBe('upload')
            ->and(File::get("$base/.env"))->toBe('APP_KEY=secret')
            ->and(File::exists("$base/update.sh"))->toBeTrue()
            ->and(thingName($base))->toBe('migrated')
            ->and(File::exists("$root/down"))->toBeFalse()
            ->and(File::exists("$state/staging"))->toBeFalse()
            ->and(File::exists("$state/handoff.json"))->toBeFalse();

        expect(File::get("$root/composer.log"))->toContain('composer install --no-dev');

        $status = json_decode(File::get("$state/status.json"), true);
        expect($status['state'])->toBe('succeeded')
            ->and($status['step'])->toBeNull()
            ->and(implode("\n", $status['log']))->not->toContain('maintenance-secret')->toContain('Updated from 1.0.0 to 1.1.0.');

        // Everything after the swap ran the release's artisan, the one moved into the install.
        expect(File::get("$root/artisan.log"))->toContain('panel: migrate --force --seed');
    } finally {
        File::deleteDirectory($root);
    }
});

it('rolls the code, database and plugin metadata back when a migration fails', function () {
    [$root, $base, $state] = fakeInstall();
    touch("$root/fail-migrate");

    try {
        $process = runUpdateScript($root, $state);
        expect($process->getExitCode())->toBe(1);

        expect(File::get("$base/app/Old.php"))->toBe('old')
            ->and(File::exists("$base/app/New.php"))->toBeFalse()
            ->and(File::get("$base/public/index.php"))->toBe('old index')
            ->and(File::get("$base/public/custom-logo.png"))->toBe('user file')
            ->and(File::exists("$base/database/migrations/old.php"))->toBeTrue()
            ->and(File::exists("$base/vendor"))->toBeFalse()
            ->and(File::exists("$base/update.sh"))->toBeFalse()
            ->and(File::get("$base/storage/app/user-upload.txt"))->toBe('upload')
            ->and(File::get("$base/.env"))->toBe('APP_KEY=secret')
            ->and(File::get("$base/plugins/example/plugin.json"))->toBe('{"meta":{"status":"enabled"}}')
            ->and(thingName($base))->toBe('before the update')
            ->and(File::exists("$root/down"))->toBeFalse();

        $status = json_decode(File::get("$state/status.json"), true);
        expect($status['state'])->toBe('failed')
            ->and($status['message'])->toContain('migration exploded');
    } finally {
        File::deleteDirectory($root);
    }
});

it('rolls back when the update is killed partway through', function () {
    [$root, $base, $state] = fakeInstall();
    touch("$root/hang-migrate");

    try {
        // What a php-fpm restart does to the update it launched, in the middle of the migrations.
        $process = new Process(['bash', "$state/staging/release/update.sh", PHP_BINARY, "$state/handoff.json"], env: ['FAKE_ROOT' => $root], timeout: 120);
        $process->start();
        $process->waitUntil(fn () => file_exists("$root/migrating"));
        $process->signal(15); // SIGTERM, pcntl may not be loaded
        $process->wait();

        expect($process->getExitCode())->toBe(1);

        expect(File::get("$base/app/Old.php"))->toBe('old')
            ->and(File::exists("$base/app/New.php"))->toBeFalse()
            ->and(File::get("$base/public/custom-logo.png"))->toBe('user file')
            ->and(File::exists("$base/vendor"))->toBeFalse()
            ->and(File::get("$base/plugins/example/plugin.json"))->toBe('{"meta":{"status":"enabled"}}')
            ->and(thingName($base))->toBe('before the update')
            ->and(File::exists("$root/down"))->toBeFalse()
            ->and(json_decode(File::get("$state/status.json"), true))
            ->state->toBe('failed')
            ->message->toContain('stopped before it finished');
    } finally {
        File::deleteDirectory($root);
    }
});

it('gives up and brings the panel back up when the queue never goes idle', function () {
    [$root, $base, $state] = fakeInstall();
    // A live artisan that always reports a running job, and a short wait.
    File::put("$base/artisan", str_replace('echo "0\n";', 'echo "1\n";', FAKE_ARTISAN));
    File::put("$state/staging/release/update.sh", str_replace('QUEUE_WAIT_SECONDS=120', 'QUEUE_WAIT_SECONDS=1', File::get("$state/staging/release/update.sh")));

    try {
        $process = runUpdateScript($root, $state);
        expect($process->getExitCode())->toBe(1);

        // Nothing was swapped, so the rollback only has to bring the panel back up.
        expect(File::get("$base/app/Old.php"))->toBe('old')
            ->and(File::exists("$root/down"))->toBeFalse()
            ->and(json_decode(File::get("$state/status.json"), true))
            ->state->toBe('failed')
            ->message->toContain('queue jobs are still running');
    } finally {
        File::deleteDirectory($root);
    }
});
