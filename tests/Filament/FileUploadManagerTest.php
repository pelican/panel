<?php

use App\Enums\SubuserPermission;
use App\Events\ActivityLogged;
use App\Livewire\FileUploadManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

it('returns the upload size limit of the server node', function () {
    [$user, $server] = generateTestAccount();

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('getUploadSizeLimit', $server->uuid)
        ->assertSuccessful()
        ->assertReturned($server->node->upload_size * 1024 * 1024);
});

it('denies users without the file create permission', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::FileRead]);

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
    [$user] = generateTestAccount();

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('getUploadSizeLimit', 'not-a-real-uuid')
        ->assertNotFound();
});

it('rejects folder paths that try to escape the server root', function () {
    [$user, $server] = generateTestAccount();

    Http::fake();

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('createFolder', $server->uuid, '../../etc', '/')
        ->assertStatus(422);

    livewire(FileUploadManager::class)
        ->call('createFolder', $server->uuid, 'safe', '/base/../../etc')
        ->assertStatus(422);

    Http::assertNothingSent();
});

it('logs uploaded files against the server', function () {
    [$user, $server] = generateTestAccount();

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('logUploadedFiles', $server->uuid, ['foo.txt', 'bar/baz.txt'], '/uploads')
        ->assertSuccessful();

    $this->assertActivityFor('server:files.uploaded', $user, $server);
});

it('creates folders through the daemon and logs the activity', function () {
    [$user, $server] = generateTestAccount();

    Http::fake(['*' => Http::response('', 200)]);

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('createFolder', $server->uuid, 'new-folder', '/base')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), "/api/servers/{$server->uuid}/files/create-directory")
        && $request['name'] === 'new-folder'
        && $request['path'] === '/base');

    $this->assertActivityFor('server:file.create-directory', $user, $server);
});

it('ignores folders that already exist', function () {
    [$user, $server] = generateTestAccount();

    Event::fake([ActivityLogged::class]);
    Http::fake(['*' => Http::response('', 400)]);

    $this->actingAs($user);

    livewire(FileUploadManager::class)
        ->call('createFolder', $server->uuid, 'existing-folder', '/base')
        ->assertSuccessful();

    Event::assertNotDispatched(ActivityLogged::class);
});

it('mounts the upload manager and the drop zone on the files page', function () {
    [$user, $server] = generateTestAccount();

    Http::fake(['*' => Http::response(['/' => []], 200)]);

    $this->actingAs($user);

    // The manager is registered as a server panel render hook, so it is on every server page.
    $this->get("/server/{$server->uuid_short}/files")
        ->assertSuccessful()
        ->assertSee('file-upload-manager', false)
        ->assertSee('fileDropZone', false)
        ->assertSee('fileBrowseButton', false);

    $this->get("/server/{$server->uuid_short}/startup")
        ->assertSuccessful()
        ->assertSee('file-upload-manager', false)
        ->assertDontSee('fileDropZone', false);
});
