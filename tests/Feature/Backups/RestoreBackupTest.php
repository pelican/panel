<?php

use App\Enums\ServerState;
use App\Enums\SubuserPermission;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Server;
use App\Repositories\Daemon\DaemonBackupRepository;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;

function restoreBackupUrl(Server $server, Backup $backup): string
{
    return "/api/client/servers/$server->uuid_short/backups/$backup->uuid/restore";
}

function makeBackup(Server $server, array $attributes = []): Backup
{
    return Backup::factory()->create([
        'server_id' => $server->id,
        'backup_host_id' => BackupHost::factory()->create()->id,
        ...$attributes,
    ]);
}

it('restores a successful backup', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::BackupRestore]);
    $backup = makeBackup($server);

    $this->mock(DaemonBackupRepository::class)
        ->expects('setServer->restore')
        ->withArgs(fn ($b, $url, $truncate) => $b->is($backup) && str_contains($url, '/download/backup?token=') && $truncate === true)
        ->andReturn(new Response(new PsrResponse()));

    $this->actingAs($user)
        ->postJson(restoreBackupUrl($server, $backup), ['truncate' => true])
        ->assertNoContent();

    expect($server->refresh()->status)->toBe(ServerState::RestoringBackup);
    $this->assertActivityFor('server:backup.restore', $user, [$backup, $server]);
});

it('refuses to restore a backup that is not successful', function (array $attributes) {
    [$user, $server] = generateTestAccount([SubuserPermission::BackupRestore]);
    $backup = makeBackup($server, $attributes);

    $this->mock(DaemonBackupRepository::class)->shouldNotReceive('setServer');

    $this->actingAs($user)
        ->postJson(restoreBackupUrl($server, $backup), ['truncate' => false])
        ->assertBadRequest();

    expect($server->refresh()->status)->toBeNull();
})->with([
    'failed' => [['is_successful' => false]],
    'in progress' => [['is_successful' => false, 'completed_at' => null]],
]);

it('refuses to restore while the server is busy', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::BackupRestore]);
    $server->update(['status' => ServerState::Installing]);
    $backup = makeBackup($server);

    $this->actingAs($user)
        ->postJson(restoreBackupUrl($server, $backup), ['truncate' => false])
        ->assertStatus(409);
});

it('requires the restore permission', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::BackupRead]);
    $backup = makeBackup($server);

    $this->actingAs($user)
        ->postJson(restoreBackupUrl($server, $backup), ['truncate' => false])
        ->assertForbidden();
});
