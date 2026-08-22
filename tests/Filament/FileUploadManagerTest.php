<?php

use App\Enums\SubuserPermission;
use App\Events\ActivityLogged;
use App\Livewire\FileUploadManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

it('returns the upload size limit of the server node', function () {
    [$user, $server] = $this->generateTestAccount();

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('getUploadSizeLimit', $server->uuid)
        ->assertSuccessful()
        ->assertReturned($server->node->upload_size * 1024 * 1024);
});

it('denies users without the file.create permission', function () {
    [$user, $server] = $this->generateTestAccount([SubuserPermission::FileRead]);

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('getUploadSizeLimit', $server->uuid)
        ->assertForbidden();

    livewire(FileUploadManager::class)
        ->call('createFolder', $server->uuid, 'folder', '/')
        ->assertForbidden();

    livewire(FileUploadManager::class)
        ->call('logUploadedFiles', $server->uuid, ['foo.txt'], '/')
        ->assertForbidden();
});

it('returns not found for an unknown server uuid', function () {
    [$user] = $this->generateTestAccount();

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('getUploadSizeLimit', 'unknown-uuid')
        ->assertNotFound();
});

it('logs uploaded files with the server as subject', function () {
    [$user, $server] = $this->generateTestAccount();

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('logUploadedFiles', $server->uuid, ['foo.txt', 'bar/baz.txt'], '/uploads')
        ->assertSuccessful();

    $this->assertActivityFor('server:files.uploaded', $user, $server);
});

it('creates folders via the daemon and logs activity', function () {
    [$user, $server] = $this->generateTestAccount();

    Http::fake([
        '*' => Http::response('', 200, ['User-Agent' => 'Pelican Wings/v1.0.0 (id:' . $server->node->daemon_token_id . ')']),
    ]);

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('createFolder', $server->uuid, 'new-folder', '/base')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), "/api/servers/{$server->uuid}/files/create-directory")
        && $request['name'] === 'new-folder'
        && $request['path'] === '/base');

    $this->assertActivityFor('server:file.create-directory', $user, $server);
});

it('renders the persisted upload manager on the files page', function () {
    [$user, $server] = $this->generateTestAccount();

    $this->actingAs($user);

    $this->get("/server/{$server->uuid_short}/files")
        ->assertSuccessful()
        ->assertSee('x-persist="file-upload-manager"', false)
        ->assertSee('server-file-upload', false);
});

it('renders the persisted upload manager on the app panel', function () {
    [$user] = $this->generateTestAccount();

    $this->actingAs($user);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('x-persist="file-upload-manager"', false);
});

it('ignores folders that already exist', function () {
    [$user, $server] = $this->generateTestAccount();

    Http::fake([
        '*' => Http::response('', 400, ['User-Agent' => 'Pelican Wings/v1.0.0 (id:' . $server->node->daemon_token_id . ')']),
    ]);

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('createFolder', $server->uuid, 'existing-folder', '/base')
        ->assertSuccessful();

    Event::assertNotDispatched(ActivityLogged::class, fn ($e) => $e->is('server:file.create-directory'));
});
