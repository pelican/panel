<?php

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The key the encrypted values in the fixture were made with.
const PTERODACTYL_FIXTURE_KEY = 'base64:5tOmfipn5OEOl75VakcH+GqKH9pezk9Xk9fZ1t/dlsQ=';

it('upgrades a Pterodactyl 1.11.4 database, even when a migration has to run again', function () {
    config(['app.key' => PTERODACTYL_FIXTURE_KEY]);
    app()->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');

    Schema::dropAllTables();
    DB::unprepared(file_get_contents(base_path('tests/_fixtures/databases/pterodactyl-1.11.4.sql')));

    $migrator = app('migrator');
    $repository = $migrator->getRepository();
    $ran = $repository->getRan();
    $batch = $repository->getNextBatchNumber();

    $writes = 0;
    DB::listen(function ($query) use (&$writes) {
        $writes += preg_match('/^\s*(select|show)\b/i', $query->sql) ? 0 : 1;
    });

    foreach ($migrator->getMigrationFiles(database_path('migrations')) as $name => $path) {
        if (in_array($name, $ran)) {
            continue;
        }

        $migration = require $path;
        $writes = 0;
        $migration->up();

        // MySQL can't roll back schema changes, so a migration that fails after its first write is left half done
        // and unrecorded, and the next `php artisan migrate` runs it again from the top.
        if ($writes > 1) {
            try {
                $migration->up();
            } catch (Throwable $exception) {
                throw new RuntimeException("$name can't be run again: {$exception->getMessage()}", previous: $exception);
            }
        }

        $repository->log($name, $batch);
    }

    $this->seed();

    $admin = DB::table('users')->where('username', 'admin')->first();
    expect(DB::table('users')->count())->toBe(2)
        ->and($admin->mfa_app_secret)->not->toBeNull()
        ->and($admin->mfa_app_recovery_codes)->not->toBeNull()
        ->and(DB::table('servers')->count())->toBe(2)
        ->and(DB::table('subusers')->where(['user_id' => 2, 'server_id' => 1])->exists())->toBeTrue()
        ->and(json_decode(DB::table('eggs')->where('name', 'Vanilla Minecraft')->value('tags')))->toContain('Quote "and" back\slash')
        ->and(json_decode(DB::table('nodes')->where('id', 1)->value('tags')))->toContain('us"east\1')
        ->and(DB::table('nodes')->where('id', 2)->value('daemon_connect'))->toBe(8443)
        ->and(DB::table('backups')->whereNull('backup_host_id')->exists())->toBeFalse()
        ->and(json_decode(DB::table('activity_logs')->find(1)->properties, true))->not->toHaveKey('password')
        ->and(decrypt(DB::table('nodes')->where('id', 1)->value('daemon_token'), false))->toBe(str_repeat('d', 64));
})->skip(fn () => !in_array(DB::getDriverName(), ['mysql', 'mariadb']), 'Pterodactyl databases are MySQL or MariaDB');
