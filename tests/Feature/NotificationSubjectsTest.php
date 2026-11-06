<?php

use App\Models\Backup;
use App\Models\BackupHost;
use App\Notifications\AccountCreated;
use App\Notifications\AddedToServer;
use App\Notifications\BackupCompleted;
use App\Notifications\RemovedFromServer;
use App\Notifications\ServerInstalled;

it('sets a subject on every notification email', function () {
    [$user, $server] = generateTestAccount();

    $backup = Backup::factory()->create([
        'server_id' => $server->id,
        'backup_host_id' => BackupHost::factory()->create()->id,
        'is_successful' => true,
    ]);

    expect((new AccountCreated())->toMail($user)->subject)->toBe('Account Created')
        ->and((new AddedToServer($server))->toMail($user)->subject)->toBe('Added to Server')
        ->and((new RemovedFromServer($server))->toMail($user)->subject)->toBe('Removed from Server')
        ->and((new ServerInstalled($server))->toMail($user)->subject)->toBe('Server Installed')
        ->and((new BackupCompleted($backup))->toMail($user)->subject)->toBe('Backup Completed');

    $backup->is_successful = false;

    expect((new BackupCompleted($backup))->toMail($user)->subject)->toBe('Backup Failed');
});

it('localizes the subject to the recipient language', function () {
    [$user, $server] = generateTestAccount();
    $user->language = 'de';

    app('translator')->addLines(['mail.server_installed.subject' => 'Server installiert'], 'de');

    expect((new ServerInstalled($server))->toMail($user)->subject)->toBe('Server installiert');
});
