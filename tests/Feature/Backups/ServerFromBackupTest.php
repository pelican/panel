<?php

use App\Enums\ServerState;
use App\Exceptions\DisplayException;
use App\Models\Allocation;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\EggVariable;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerRestore;
use App\Models\ServerVariable;
use App\Models\User;
use App\Policies\BackupPolicy;
use App\Repositories\Daemon\DaemonBackupRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Backups\BackupConfigResolver;
use App\Services\Backups\InitiateBackupService;
use App\Services\Backups\RestoreSource;
use App\Services\Backups\ServerConfigExporterService;
use App\Services\Backups\ServerConfigImporterService;
use App\Services\Backups\ServerRestoreService;
use App\Services\Servers\ServerDeletionService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * A server whose egg has one variable users can see and one they cannot.
 *
 * @return array{Server, EggVariable, EggVariable}
 */
function serverWithVariables(): array
{
    $egg = cloneEggAndVariables(getBungeecordEgg());
    $egg->variables()->delete();

    $visible = $egg->variables()->create([
        'name' => 'Visible', 'description' => '', 'env_variable' => 'VISIBLE_VAR', 'default_value' => 'default',
        'user_viewable' => true, 'user_editable' => true, 'rules' => ['nullable', 'string'], 'sort' => 1,
    ]);
    $hidden = $egg->variables()->create([
        'name' => 'Hidden', 'description' => '', 'env_variable' => 'HIDDEN_VAR', 'default_value' => 'hidden-default',
        'user_viewable' => false, 'user_editable' => false, 'rules' => ['nullable', 'string'], 'sort' => 2,
    ]);

    $server = createServerModel(['egg_id' => $egg->id, 'memory' => 2048, 'swap' => -1, 'backup_limit' => 3]);
    ServerVariable::query()->create(['server_id' => $server->id, 'variable_id' => $visible->id, 'variable_value' => 'visible-value']);
    ServerVariable::query()->create(['server_id' => $server->id, 'variable_id' => $hidden->id, 'variable_value' => 'secret-value']);

    Allocation::factory()->create(['node_id' => $server->node_id, 'server_id' => $server->id, 'ip' => $server->allocation->ip, 'port' => 25570]);

    return [$server->refresh(), $visible, $hidden];
}

/**
 * Builds a gzipped tar archive with the given entries, in order.
 *
 * @param  array<string, string>  $entries
 */
function tarGz(array $entries): string
{
    $tar = '';
    foreach ($entries as $name => $content) {
        $header = str_pad($name, 100, "\0")
            . str_pad('0000644', 8, "\0")
            . str_pad('0000000', 8, "\0")
            . str_pad('0000000', 8, "\0")
            . str_pad(decoct(strlen($content)), 11, '0', STR_PAD_LEFT) . "\0"
            . str_pad(decoct(time()), 11, '0', STR_PAD_LEFT) . "\0"
            . str_repeat(' ', 8)
            . '0'
            . str_repeat("\0", 100)
            . "ustar\0" . '00'
            . str_repeat("\0", 247);
        $checksum = array_sum(array_map('ord', str_split($header)));
        $header = substr_replace($header, str_pad(decoct($checksum), 6, '0', STR_PAD_LEFT) . "\0 ", 148, 8);

        $tar .= $header . str_pad($content, (int) ceil(strlen($content) / 512) * 512, "\0");
    }

    return gzencode($tar . str_repeat("\0", 1024));
}

it('exports only the variables users can see', function () {
    [$server] = serverWithVariables();

    $config = app(ServerConfigExporterService::class)->handle($server);

    expect($config['meta']['version'])->toBe(ServerConfigExporterService::VERSION)
        ->and($config['egg']['uuid'])->toBe($server->egg->uuid)
        ->and((array) $config['variables'])->toBe(['VISIBLE_VAR' => 'visible-value'])
        ->and($config['limits']['memory'])->toBe(2048)
        ->and($config['feature_limits']['backups'])->toBe(3)
        ->and($config['allocations']['default']['port'])->toBe($server->allocation->port)
        ->and($config['allocations']['additional'])->toHaveCount(1)
        ->and($config['allocations']['additional'][0]['port'])->toBe(25570)
        ->and($config)->not->toHaveKey('owner')
        ->and($config['server'])->not->toHaveKeys(['owner_uuid', 'node_uuid']);
});

it('stores the configuration with a backup unless it is turned off', function () {
    config(['backups.throttles.period' => 0]);
    BackupHost::factory()->create();
    $daemon = $this->mock(DaemonBackupRepository::class);
    $daemon->allows('setServer')->andReturnSelf();
    $daemon->allows('create')->andReturn(new Response(new PsrResponse()));

    [$server] = serverWithVariables();

    $with = app(InitiateBackupService::class)->handle($server, 'with');
    $without = app(InitiateBackupService::class)->setIncludeConfig(false)->handle($server, 'without');

    expect($with->pelican_config['server']['uuid'])->toBe($server->uuid)
        ->and($with->server_uuid)->toBe($server->uuid)
        ->and($with->node_id)->toBe($server->node_id)
        ->and($without->pelican_config)->toBeNull();
});

it('turns a configuration into the create server form state', function () {
    [$server, $visible, $hidden] = serverWithVariables();
    $config = app(ServerConfigExporterService::class)->handle($server);
    $config = json_decode(json_encode($config), true);

    $importer = app(ServerConfigImporterService::class);
    $state = $importer->toFormState($importer->parse($config));

    expect($state['egg_id'])->toBe($server->egg_id)
        ->and($state['skip_scripts'])->toBe(1)
        ->and($state['memory'])->toBe(2048)
        ->and($state['unlimited_mem'])->toBe(0)
        ->and($state['swap_support'])->toBe('unlimited')
        ->and($state['backup_limit'])->toBe(3)
        ->and($state['image'])->toBe($server->image)
        ->and($state['environment'])->toBe(['VISIBLE_VAR' => 'visible-value', 'HIDDEN_VAR' => 'hidden-default'])
        ->and($state)->not->toHaveKeys(['owner_id', 'node_id']);
});

it('rejects configurations it does not understand', function () {
    app(ServerConfigImporterService::class)->parse(['meta' => ['version' => 'PLCN_SRV_v999']]);
})->throws(DisplayException::class);

it('matches the original allocations when they are free', function () {
    [$server] = serverWithVariables();
    $config = json_decode(json_encode(app(ServerConfigExporterService::class)->handle($server)), true);
    $node = $server->node;
    $primary = $server->allocation;
    $additional = $server->allocations()->where('port', 25570)->first();

    $importer = app(ServerConfigImporterService::class);

    // Still in use by the original server.
    expect($importer->matchAllocations($config, $node)['missing'])->toHaveCount(2);

    $server->allocations()->update(['server_id' => null]);

    expect($importer->matchAllocations($config, $node))->toBe([
        'allocation_id' => $primary->id,
        'allocation_additional' => [$additional->id],
        'missing' => [],
    ])->and($importer->matchAllocations($config, Node::factory()->create())['missing'])
        ->toBe(["$primary->ip:$primary->port", "$primary->ip:25570"]);
});

it('reads the configuration from the start of an archive', function () {
    $resolver = app(BackupConfigResolver::class);
    $config = ['meta' => ['version' => ServerConfigExporterService::VERSION]];

    $archive = tarGz(['.pelicanconfig' => json_encode($config), 'server.properties' => 'motd=hi']);
    expect($resolver->fromArchiveStart($archive))->toBe($config);

    // Only the first part of a large archive is downloaded.
    expect($resolver->fromArchiveStart(substr($archive, 0, 200)))->toBe($config);

    $other = tarGz(['server.properties' => 'motd=hi', '.pelicanconfig' => json_encode($config)]);
    expect($resolver->fromArchiveStart($other))->toBeNull()
        ->and($resolver->fromArchiveStart('not an archive'))->toBeNull();
});

it('keeps finished backups when deleting a server if asked', function () {
    $host = BackupHost::factory()->create();
    [$server] = serverWithVariables();
    $kept = Backup::factory()->create([
        'server_id' => $server->id, 'server_uuid' => $server->uuid, 'node_id' => $server->node_id,
        'backup_host_id' => $host->id, 'pelican_config' => ['meta' => ['version' => ServerConfigExporterService::VERSION]],
    ]);
    $failed = Backup::factory()->create([
        'server_id' => $server->id, 'server_uuid' => $server->uuid, 'node_id' => $server->node_id,
        'backup_host_id' => $host->id, 'is_successful' => false,
    ]);

    $daemonServer = $this->mock(DaemonServerRepository::class);
    $daemonServer->expects('setServer')->andReturnSelf();
    $daemonServer->expects('delete')->with(true);
    $daemonBackup = $this->mock(DaemonBackupRepository::class);
    $daemonBackup->allows('setServer')->andReturnSelf();
    $daemonBackup->expects('delete')->andReturn(new Response(new PsrResponse()));

    app(ServerDeletionService::class)->keepBackups()->handle($server);

    $kept->refresh();
    expect(Server::query()->find($server->id))->toBeNull()
        ->and($kept->server_id)->toBeNull()
        ->and($kept->sourceServerUuid())->toBe($server->uuid)
        ->and($kept->isOrphaned())->toBeTrue()
        ->and(Backup::query()->find($failed->id))->toBeNull()
        ->and(Backup::query()->orphaned()->pluck('id')->all())->toBe([$kept->id]);
});

it('deletes backups with the server by default', function () {
    $host = BackupHost::factory()->create();
    [$server] = serverWithVariables();
    $backup = Backup::factory()->create(['server_id' => $server->id, 'server_uuid' => $server->uuid, 'backup_host_id' => $host->id]);

    $daemonServer = $this->mock(DaemonServerRepository::class);
    $daemonServer->expects('setServer')->andReturnSelf();
    $daemonServer->expects('delete')->withNoArgs();

    app(ServerDeletionService::class)->handle($server);

    expect(Backup::query()->find($backup->id))->toBeNull();
});

it('restores a backup from the same node into a new server', function () {
    $host = BackupHost::factory()->create();
    [$old] = serverWithVariables();
    $backup = Backup::factory()->create(['server_id' => null, 'server_uuid' => $old->uuid, 'node_id' => $old->node_id, 'backup_host_id' => $host->id]);
    $new = createServerModel(['node_id' => $old->node_id]);

    $daemon = $this->mock(DaemonBackupRepository::class);
    $daemon->allows('setServer')->andReturnSelf();
    $daemon->expects('restoreInto')->with($backup->uuid, 'wings', $old->uuid, null)->andReturn(new Response(new PsrResponse(202)));

    $service = app(ServerRestoreService::class);
    $restore = $service->create($new, RestoreSource::backup($backup), true);
    $service->start($restore->refresh());

    expect($restore->refresh()->status)->toBe(ServerRestore::STATUS_RUNNING)
        ->and($new->refresh()->status)->toBe(ServerState::RestoringBackup)
        ->and($service->findRunning($backup->uuid, $new->node)?->id)->toBe($restore->id)
        ->and($service->findRunning($backup->uuid, Node::factory()->create()))->toBeNull();

    $power = $this->mock(DaemonServerRepository::class);
    $power->expects('setServer')->andReturnSelf();
    $power->expects('power')->with('start')->andReturn(new Response(new PsrResponse(204)));
    app(ServerRestoreService::class)->complete($restore, true);

    expect($restore->refresh()->status)->toBe(ServerRestore::STATUS_COMPLETED)
        ->and($new->refresh()->status)->toBeNull()
        // The owner hears that the files are in place, not only that the install finished.
        ->and($new->user->notifications()->get()->pluck('data.title')->all())
        ->toContain(trans('admin/backup.restore.completed_title', ['server' => $new->name]));
});

it('downloads a wings backup from its node when restoring on another node', function () {
    $host = BackupHost::factory()->create();
    [$old] = serverWithVariables();
    $backup = Backup::factory()->create(['server_id' => $old->id, 'server_uuid' => $old->uuid, 'node_id' => $old->node_id, 'backup_host_id' => $host->id]);
    $new = createServerModel();

    $daemon = $this->mock(DaemonBackupRepository::class);
    $daemon->allows('setServer')->andReturnSelf();
    $daemon->expects('restoreInto')->withArgs(fn ($uuid, $adapter, $source, $url) => $uuid === $backup->uuid && $adapter === 'wings' && $source === $old->uuid
        && str_starts_with($url, $old->node->getConnectionAddress() . '/download/backup?token='))
        ->andReturn(new Response(new PsrResponse(202)));

    $service = app(ServerRestoreService::class);
    $service->start($service->create($new, RestoreSource::backup($backup), false));
});

it('restores from a link using the restore identifier', function () {
    $new = createServerModel();

    $daemon = $this->mock(DaemonBackupRepository::class);
    $daemon->allows('setServer')->andReturnSelf();
    $daemon->expects('restoreInto')->withArgs(fn ($uuid, $adapter, $source, $url) => $adapter === 's3' && $source === null && $url === 'https://example.com/backup.tar.gz')
        ->andReturn(new Response(new PsrResponse(202)));

    $service = app(ServerRestoreService::class);
    $restore = $service->create($new, RestoreSource::url('https://example.com/backup.tar.gz'), false);
    $service->start($restore);

    expect($service->findRunning($restore->uuid, $new->node)?->id)->toBe($restore->id);

    $service->complete($restore->refresh(), false);
    expect($restore->refresh()->status)->toBe(ServerRestore::STATUS_FAILED)
        ->and($new->refresh()->status)->toBeNull();
});

it('keeps reports of two restores of the same backup on one node apart', function () {
    $host = BackupHost::factory()->create();
    [$old] = serverWithVariables();
    $backup = Backup::factory()->create(['server_id' => null, 'server_uuid' => $old->uuid, 'node_id' => $old->node_id, 'backup_host_id' => $host->id]);
    $first = createServerModel(['node_id' => $old->node_id]);
    $second = createServerModel(['node_id' => $old->node_id]);

    $daemon = $this->mock(DaemonBackupRepository::class);
    $daemon->allows('setServer')->andReturnSelf();
    $daemon->expects('restoreInto')->with($backup->uuid, 'wings', $old->uuid, null)->andReturn(new Response(new PsrResponse(202)));
    $daemon->expects('restoreInto')->withArgs(fn ($uuid, $adapter, $source, $url) => $uuid !== $backup->uuid && $adapter === 's3' && $source === null
        && str_starts_with($url, $old->node->getConnectionAddress() . '/download/backup?token='))
        ->andReturn(new Response(new PsrResponse(202)));

    $service = app(ServerRestoreService::class);
    $firstRestore = $service->create($first, RestoreSource::backup($backup), false);
    $secondRestore = $service->create($second, RestoreSource::backup($backup), false);
    $service->start($firstRestore);
    $service->start($secondRestore);

    expect($service->findRunning($backup->uuid, $old->node)?->id)->toBe($firstRestore->id)
        ->and($service->findRunning($secondRestore->uuid, $old->node)?->id)->toBe($secondRestore->id);
});

it('starts a restore only once', function () {
    $new = createServerModel();

    $daemon = $this->mock(DaemonBackupRepository::class);
    $daemon->allows('setServer')->andReturnSelf();
    $daemon->expects('restoreInto')->once()->andReturn(new Response(new PsrResponse(202)));

    $service = app(ServerRestoreService::class);
    $restore = $service->create($new, RestoreSource::url('https://example.com/backup.tar.gz'), false);
    $service->start($restore);
    $service->start(ServerRestore::query()->find($restore->id));

    expect($restore->refresh()->status)->toBe(ServerRestore::STATUS_RUNNING);
});

it('keeps a server suspended during its restore suspended and stopped', function () {
    $new = createServerModel(['status' => null]);

    $daemon = $this->mock(DaemonBackupRepository::class);
    $daemon->allows('setServer')->andReturnSelf();
    $daemon->allows('restoreInto')->andReturn(new Response(new PsrResponse(202)));
    $this->mock(DaemonServerRepository::class)->expects('power')->never();

    $service = app(ServerRestoreService::class);
    $restore = $service->create($new, RestoreSource::url('https://example.com/backup.tar.gz'), true);
    $service->start($restore);

    $new->refresh()->update(['status' => ServerState::Suspended]);
    $service->complete($restore->refresh(), true);

    expect($new->refresh()->status)->toBe(ServerState::Suspended)
        ->and($restore->refresh()->status)->toBe(ServerRestore::STATUS_COMPLETED);
});

it('accepts configurations with empty lists and pinning to cpu 0', function () {
    [$server] = serverWithVariables();
    $config = json_decode(json_encode(app(ServerConfigExporterService::class)->handle($server)), true);
    $config['variables'] = null;
    $config['allocations']['additional'] = null;
    $config['limits']['threads'] = '0';

    $importer = app(ServerConfigImporterService::class);
    $parsed = $importer->parse($config);
    $state = $importer->toFormState($parsed);

    expect($state['environment'])->toBe(['VISIBLE_VAR' => 'default', 'HIDDEN_VAR' => 'hidden-default'])
        ->and($state['cpu_pinning'])->toBe(1)
        ->and($state['threads'])->toBe(['0'])
        ->and($importer->matchAllocations($parsed, $server->node)['missing'])->toHaveCount(1);
});

it('only inflates as much of an archive as the configuration needs', function () {
    $resolver = app(BackupConfigResolver::class);
    $config = ['meta' => ['version' => ServerConfigExporterService::VERSION]];

    // 64 MiB of zeros compresses to far less than what is downloaded.
    $archive = substr(tarGz(['.pelicanconfig' => json_encode($config), 'world.dat' => str_repeat("\0", 64 * 1024 * 1024)]), 0, BackupConfigResolver::MAX_READ_BYTES);
    gc_collect_cycles();
    memory_reset_peak_usage();
    $before = memory_get_usage();

    expect($resolver->fromArchiveStart($archive))->toBe($config)
        ->and(memory_get_peak_usage() - $before)->toBeLessThan(16 * 1024 * 1024);
});

it('does not fetch links to internal addresses', function () {
    Http::fake();
    $resolver = app(BackupConfigResolver::class);

    foreach (['http://127.0.0.1/backup.tar.gz', 'http://10.0.0.5/backup.tar.gz', 'http://[::1]/backup.tar.gz', 'http://169.254.169.254/latest', 'http://100.64.0.1/backup.tar.gz', 'http://198.18.0.1/backup.tar.gz'] as $url) {
        expect($resolver->isPublicUrl($url))->toBeFalse();
        expect($resolver->resolve(RestoreSource::url($url)))->toBeNull();
    }

    Http::assertNothingSent();
});

it('follows redirects of a link to read its configuration', function () {
    $config = ['meta' => ['version' => ServerConfigExporterService::VERSION], 'server' => ['name' => 'from-a-release']];
    Http::fake([
        'https://93.184.215.14/download/backup.tar.gz' => Http::response('', 302, ['Location' => 'https://1.1.1.1/assets/backup.tar.gz?sig=1']),
        'https://1.1.1.1/assets/backup.tar.gz*' => Http::response(tarGz(['.pelicanconfig' => json_encode($config)]), 200, ['Content-Type' => 'application/octet-stream']),
    ]);

    expect(app(BackupConfigResolver::class)->resolve(RestoreSource::url('https://93.184.215.14/download/backup.tar.gz')))
        ->toBe($config);
});

it('does not follow a redirect of a link to an internal address', function () {
    Http::fake([
        'https://93.184.215.14/*' => Http::response('', 302, ['Location' => 'http://10.0.0.5/backup.tar.gz']),
        '*' => Http::response('should not be requested'),
    ]);

    expect(app(BackupConfigResolver::class)->resolve(RestoreSource::url('https://93.184.215.14/backup.tar.gz')))->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '10.0.0.5'));
});

it('gives up on a link that redirects to a location that is not a link', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'http:///x'])]);

    expect(app(BackupConfigResolver::class)->resolve(RestoreSource::url('https://93.184.215.14/backup.tar.gz')))->toBeNull();

    Http::assertSentCount(1);
});

it('gives up on a link after a few redirects', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://93.184.215.14/again'])]);

    expect(app(BackupConfigResolver::class)->resolve(RestoreSource::url('https://93.184.215.14/start')))->toBeNull();

    Http::assertSentCount(BackupConfigResolver::MAX_REDIRECTS + 1);
});

it('keeps the exact S3 object key, including a leading slash', function () {
    $host = BackupHost::factory()->create(['schema' => 's3']);

    expect(RestoreSource::s3($host, ' /snapshots/server.tar.gz ')->key)->toBe('/snapshots/server.tar.gz')
        ->and(RestoreSource::fromArray(RestoreSource::s3($host, '/snapshots/server.tar.gz')->toArray())->key)->toBe('/snapshots/server.tar.gz');
});

it('only accepts http links as a restore source', function () {
    RestoreSource::url('file:///etc/passwd');
})->throws(InvalidArgumentException::class);

it('lets only administrators who can create servers see all backups', function () {
    $policy = new BackupPolicy();

    expect($policy->viewAny(User::factory()->create()))->toBeFalse()
        ->and($policy->create(User::factory()->create()))->toBeFalse();
});

it('still deletes backups with a server deleted outside the deletion service', function () {
    $host = BackupHost::factory()->create();
    [$server] = serverWithVariables();
    $backup = Backup::factory()->create(['server_id' => $server->id, 'server_uuid' => $server->uuid, 'backup_host_id' => $host->id]);

    $server->allocations()->update(['server_id' => null]);
    $server->delete();

    expect(Backup::query()->find($backup->id))->toBeNull();
});

it('does not restore a backup in place while it is restored into a new server on the same node', function () {
    $host = BackupHost::factory()->create();
    [$server] = serverWithVariables();
    $server->update(['status' => null]);
    $backup = Backup::factory()->create(['server_id' => $server->id, 'server_uuid' => $server->uuid, 'node_id' => $server->node_id, 'backup_host_id' => $host->id]);
    $new = createServerModel(['node_id' => $server->node_id]);

    $daemon = $this->mock(DaemonBackupRepository::class);
    $daemon->allows('setServer')->andReturnSelf();
    $daemon->expects('restoreInto')->andReturn(new Response(new PsrResponse(202)));
    $daemon->expects('restore')->never();

    $service = app(ServerRestoreService::class);
    $service->start($service->create($new, RestoreSource::backup($backup), false));

    expect($service->isRestoringIntoNewServer($backup, $server->node_id))->toBeTrue();

    $this->actingAs($server->user)
        ->postJson("/api/client/servers/$server->uuid/backups/$backup->uuid/restore", ['truncate' => false])
        ->assertBadRequest();

    expect($server->refresh()->status)->toBeNull();
});

it('fills in the server and node of existing backups when migrating', function () {
    $host = BackupHost::factory()->create();
    [$server] = serverWithVariables();
    $backup = Backup::factory()->create(['server_id' => $server->id, 'backup_host_id' => $host->id]);
    DB::table('backups')->where('id', $backup->id)->update(['server_uuid' => null, 'node_id' => null]);

    (require database_path('migrations/2026_11_11_000000_add_restore_columns_to_backups_table.php'))->up();

    $backup->refresh();
    expect($backup->server_uuid)->toBe($server->uuid)
        ->and($backup->node_id)->toBe($server->node_id);
});

it('holds the backup restore lock while an in-place restore starts', function () {
    $host = BackupHost::factory()->create();
    [$server] = serverWithVariables();
    $server->update(['status' => null]);
    $backup = Backup::factory()->create(['server_id' => $server->id, 'server_uuid' => $server->uuid, 'node_id' => $server->node_id, 'backup_host_id' => $host->id]);

    $daemon = $this->mock(DaemonBackupRepository::class);
    $daemon->allows('setServer')->andReturnSelf();
    // A new-server restore of the same backup can't start while the node is asked to restore it in place.
    $daemon->expects('restore')->andReturnUsing(function () use ($backup) {
        expect(Cache::lock("backup-restore:$backup->uuid")->get())->toBeFalse();

        return new Response(new PsrResponse(204));
    });

    $this->actingAs($server->user)
        ->postJson("/api/client/servers/$server->uuid/backups/$backup->uuid/restore", ['truncate' => false])
        ->assertNoContent();

    expect($server->refresh()->status)->toBe(ServerState::RestoringBackup)
        ->and(Cache::lock("backup-restore:$backup->uuid")->get())->toBeTrue();
});

it('holds the backup restore lock while a new-server restore records how the node reports it', function () {
    $host = BackupHost::factory()->create();
    [$old] = serverWithVariables();
    $backup = Backup::factory()->create(['server_id' => null, 'server_uuid' => $old->uuid, 'node_id' => $old->node_id, 'backup_host_id' => $host->id]);
    $new = createServerModel(['node_id' => $old->node_id]);

    $daemon = $this->mock(DaemonBackupRepository::class);
    $daemon->allows('setServer')->andReturnSelf();
    $daemon->allows('restoreInto')->andReturn(new Response(new PsrResponse(202)));

    $service = app(ServerRestoreService::class);
    $restore = $service->create($new, RestoreSource::backup($backup), false);

    $lockedWhileRecorded = null;
    ServerRestore::updating(function (ServerRestore $model) use ($backup, &$lockedWhileRecorded) {
        if ($model->isDirty('callback_uuid')) {
            $lockedWhileRecorded = !Cache::lock("backup-restore:$backup->uuid")->get();
        }
    });

    $service->start($restore);

    expect($lockedWhileRecorded)->toBeTrue()
        ->and($restore->refresh()->callback_uuid)->toBe($backup->uuid);
});

it('holds the backup restore lock for longer than the longest daemon request', function () {
    config(['panel.guzzle.timeout' => 60, 'panel.guzzle.connect_timeout' => 60]);

    expect(app(ServerRestoreService::class)->lockSeconds())->toBe(180);
});
