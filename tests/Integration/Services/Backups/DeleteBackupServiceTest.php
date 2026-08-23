<?php

namespace App\Tests\Integration\Services\Backups;

use App\Exceptions\Service\Backup\BackupLockedException;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Repositories\Daemon\DaemonBackupRepository;
use App\Services\Backups\DeleteBackupService;
use App\Tests\Integration\IntegrationTestCase;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;

class DeleteBackupServiceTest extends IntegrationTestCase
{
    public function test_locked_backup_cannot_be_deleted(): void
    {
        $server = $this->createServerModel();

        $backupHost = BackupHost::factory()->create();
        $backup = Backup::factory()->create([
            'server_id' => $server->id,
            'backup_host_id' => $backupHost->id,
            'is_locked' => true,
        ]);

        $this->expectException(BackupLockedException::class);

        $this->app->make(DeleteBackupService::class)->handle($backup);

        $this->assertModelExists($backup);
    }

    public function test_failed_backup_that_is_locked_can_be_deleted(): void
    {
        $server = $this->createServerModel();

        $backupHost = BackupHost::factory()->create();
        $backup = Backup::factory()->create([
            'server_id' => $server->id,
            'backup_host_id' => $backupHost->id,
            'is_locked' => true,
            'is_successful' => false,
        ]);

        $mock = $this->mock(DaemonBackupRepository::class);
        $mock->expects('setServer->delete')->with($backup)->andReturn(new Response());

        $this->app->make(DeleteBackupService::class)->handle($backup);

        $this->assertModelMissing($backup);
    }

    public function test_exception_thrown_due_to_missing_backup_is_ignored(): void
    {
        $server = $this->createServerModel();

        $backupHost = BackupHost::factory()->create();
        $backup = Backup::factory()->create(['server_id' => $server->id, 'backup_host_id' => $backupHost->id]);

        $mock = $this->mock(DaemonBackupRepository::class);
        $mock->expects('setServer->delete')->with($backup)->andThrow(new ConnectionException(code: 404));

        $this->app->make(DeleteBackupService::class)->handle($backup);

        $this->assertModelMissing($backup);
    }

    public function test_exception_is_thrown_if_not404(): void
    {
        $server = $this->createServerModel();

        $backupHost = BackupHost::factory()->create();
        $backup = Backup::factory()->create(['server_id' => $server->id, 'backup_host_id' => $backupHost->id]);

        $mock = $this->mock(DaemonBackupRepository::class);
        $mock->expects('setServer->delete')->with($backup)->andThrow(new ConnectionException(code: 500));

        $this->expectException(ConnectionException::class);

        $this->app->make(DeleteBackupService::class)->handle($backup);

        $this->assertModelExists($backup);
    }
}
