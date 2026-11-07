<?php

use App\Exceptions\Service\Backup\TooManyBackupsException;
use App\Extensions\Tasks\Schemas\CreateBackupSchema;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\Task;
use App\Repositories\Daemon\DaemonBackupRepository;
use App\Services\Backups\InitiateBackupService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

beforeEach(function () {
    config(['backups.throttles.period' => 0]);
    BackupHost::factory()->create();
    $this->daemon = $this->mock(DaemonBackupRepository::class);
    $this->daemon->allows('setServer')->andReturnSelf();
    $this->daemon->allows('create')->andReturn(new Response(new PsrResponse()));
});

function serverWithBackups(int $limit, int $existing, array $attributes = []): Server
{
    [, $server] = generateTestAccount();
    $server->update(['backup_limit' => $limit]);

    for ($i = 0; $i < $existing; $i++) {
        Backup::factory()->create([
            'server_id' => $server->id,
            'backup_host_id' => BackupHost::first()->id,
            'created_at' => now()->subHours($existing - $i),
            ...$attributes,
        ]);
    }

    return $server;
}

it('creates a backup', function () {
    $server = serverWithBackups(2, 0);

    $backup = app(InitiateBackupService::class)
        ->setIgnoredFiles(['logs', '', 'cache'])
        ->setIsLocked(true)
        ->handle($server, 'nightly');

    expect($backup)
        ->name->toBe('nightly')
        ->ignored_files->toBe(['logs', 'cache'])
        ->is_locked->toBeTrue()
        ->is_scheduled->toBeFalse();
});

it('refuses when the server is at its limit without override', function () {
    $server = serverWithBackups(1, 1);

    app(InitiateBackupService::class)->handle($server);
})->throws(TooManyBackupsException::class);

it('refuses when the server has no backup slots even with override', function () {
    $server = serverWithBackups(0, 0);

    app(InitiateBackupService::class)->handle($server, null, true);
})->throws(TooManyBackupsException::class);

it('rotates the oldest unlocked backup with override', function () {
    $server = serverWithBackups(2, 2);
    $oldest = $server->backups()->oldest()->first();
    $this->daemon->expects('delete')->andReturn(new Response(new PsrResponse()));

    app(InitiateBackupService::class)->handle($server, null, true);

    expect(Backup::find($oldest->id))->toBeNull()
        ->and($server->backups()->count())->toBe(2);
});

it('refuses to rotate when every backup is locked', function () {
    $server = serverWithBackups(1, 1, ['is_locked' => true]);

    app(InitiateBackupService::class)->handle($server, null, true);
})->throws(TooManyBackupsException::class);

it('throttles backup creation', function () {
    config(['backups.throttles.limit' => 1, 'backups.throttles.period' => 600]);
    $server = serverWithBackups(5, 0);
    Backup::factory()->create(['server_id' => $server->id, 'backup_host_id' => BackupHost::first()->id]);

    app(InitiateBackupService::class)->handle($server);
})->throws(TooManyRequestsHttpException::class);

it('marks and rotates backups created by a schedule task', function () {
    $server = serverWithBackups(1, 1);
    $old = $server->backups()->first();
    $this->daemon->expects('delete')->andReturn(new Response(new PsrResponse()));

    $schedule = Schedule::factory()->create(['server_id' => $server->id]);
    $task = Task::factory()->create(['schedule_id' => $schedule->id, 'action' => 'backup', 'payload' => "logs\ncache"]);

    app(CreateBackupSchema::class)->runTask($task);

    $backup = $server->backups()->sole();
    expect($backup->id)->not->toBe($old->id)
        ->and($backup->is_scheduled)->toBeTrue()
        ->and($backup->ignored_files)->toBe(['logs', 'cache']);
});
