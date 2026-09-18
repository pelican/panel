<?php

namespace App\Tests\Integration\Api\Client\Server\Backup;

use App\Models\ActivityLog;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Repositories\Daemon\DaemonBackupRepository;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class CreateBackupTest extends ClientApiIntegrationTestCase
{
    public function test_backup_can_be_created_and_logs_one_activity(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $server->update(['backup_limit' => 1]);
        BackupHost::factory()->create();

        $this->mock(DaemonBackupRepository::class)
            ->expects('setServer->create')
            ->withArgs(fn ($backup) => $backup instanceof Backup && $backup->server_id === $server->id);

        $this->actingAs($user)->postJson($this->link($server, '/backups'), [
            'name' => 'Regression backup',
        ])->assertOk()->assertJsonPath('attributes.name', 'Regression backup');

        $this->assertSame(1, Backup::query()->where('server_id', $server->id)->count());
        $this->assertSame(1, ActivityLog::query()->where('event', 'server:backup.start')->count());
    }
}
