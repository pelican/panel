<?php

use App\Models\Backup;
use App\Models\BackupHost;
use App\Services\Backups\DownloadLinkService;

it('returns a signed wings download link', function () {
    [$user, $server] = generateTestAccount();
    $backup = Backup::factory()->create(['server_id' => $server->id, 'backup_host_id' => BackupHost::factory()->create()->id]);

    $url = app(DownloadLinkService::class)->handle($backup, $user);

    expect($url)->toStartWith($server->node->getConnectionAddress() . '/download/backup?token=');
});

it('throws for an unknown backup adapter', function () {
    [$user, $server] = generateTestAccount();
    $backup = Backup::factory()->create(['server_id' => $server->id, 'backup_host_id' => BackupHost::factory()->create(['schema' => 'nope'])->id]);

    app(DownloadLinkService::class)->handle($backup, $user);
})->throws(Exception::class, 'Backup has unknown backup adapter.');
