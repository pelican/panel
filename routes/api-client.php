<?php

use App\Enums\ResourceLimit;
use App\Http\Controllers\Api\Client;
use App\Http\Middleware\Activity\AccountSubject;
use App\Http\Middleware\Activity\ServerSubject;
use App\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use App\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Client Control API
|--------------------------------------------------------------------------
|
| Endpoint: /api/client
|
*/
Route::get('/', [Client\ClientController::class, 'index'])->name('api:client.index');
Route::get('/permissions', [Client\ClientController::class, 'permissions']);

Route::prefix('/account')->middleware(AccountSubject::class)->group(function () {
    Route::get('/', [Client\AccountController::class, 'index'])->name('api:client.account');

    Route::put('/username', [Client\AccountController::class, 'updateUsername'])->name('api:client.account.update-username');
    Route::put('/email', [Client\AccountController::class, 'updateEmail'])
        ->middleware('throttle')
        ->name('api:client.account.update-email');
    Route::put('/password', [Client\AccountController::class, 'updatePassword'])->name('api:client.account.update-password');

    Route::get('/activity', Client\ActivityLogController::class)->name('api:client.account.activity');

    Route::prefix('/api-keys')->group(function () {
        Route::get('/', [Client\ApiKeyController::class, 'index']);
        Route::post('/', [Client\ApiKeyController::class, 'store']);
        Route::delete('/{identifier}', [Client\ApiKeyController::class, 'delete']);
    });

    Route::prefix('/ssh-keys')->group(function () {
        Route::get('/', [Client\SSHKeyController::class, 'index']);
        Route::post('/', [Client\SSHKeyController::class, 'store']);
        Route::delete('/{fingerprint}', [Client\SSHKeyController::class, 'delete']);
    });
});

/*
|--------------------------------------------------------------------------
| Client Control API
|--------------------------------------------------------------------------
|
| Endpoint: /api/client/servers/{server}
|
*/
Route::prefix('/servers/{server:uuid}')->middleware([ServerSubject::class, AuthenticateServerAccess::class, ResourceBelongsToServer::class])->group(function () {
    Route::get('/', [Client\Servers\ServerController::class, 'index'])->name('api:client:server.view');
    Route::middleware([ResourceLimit::Websocket->middleware()])
        ->get('/websocket', Client\Servers\WebsocketController::class)
        ->name('api:client:server.ws');
    Route::get('/resources', Client\Servers\ResourceUtilizationController::class)->name('api:client:server.resources');
    Route::get('/activity', Client\Servers\ActivityLogController::class)->name('api:client:server.activity');

    Route::post('/command', [Client\Servers\CommandController::class, 'index']);
    Route::post('/power', [Client\Servers\PowerController::class, 'index']);

    Route::prefix('/databases')->group(function () {
        Route::get('/', [Client\Servers\DatabaseController::class, 'index']);
        Route::middleware([ResourceLimit::DatabaseCreate->middleware()])
            ->post('/', [Client\Servers\DatabaseController::class, 'store']);
        Route::post('/{database}/rotate-password', [Client\Servers\DatabaseController::class, 'rotatePassword']);
        Route::delete('/{database}', [Client\Servers\DatabaseController::class, 'delete']);
    });

    Route::prefix('/files')->name('api:client:server.files.')->group(function () {
        Route::get('/list', [Client\Servers\FileController::class, 'directory'])->name('list');
        Route::get('/contents', [Client\Servers\FileController::class, 'contents'])->name('contents');
        Route::get('/download', [Client\Servers\FileController::class, 'download'])->name('download');
        Route::put('/rename', [Client\Servers\FileController::class, 'rename'])->name('rename');
        Route::post('/copy', [Client\Servers\FileController::class, 'copy'])->name('copy');
        Route::post('/write', [Client\Servers\FileController::class, 'write'])->name('write');
        Route::post('/compress', [Client\Servers\FileController::class, 'compress'])->name('compress');
        Route::post('/decompress', [Client\Servers\FileController::class, 'decompress'])->name('decompress');
        Route::post('/delete', [Client\Servers\FileController::class, 'delete'])->name('delete');
        Route::post('/create-folder', [Client\Servers\FileController::class, 'create'])->name('create-folder');
        Route::post('/chmod', [Client\Servers\FileController::class, 'chmod'])->name('chmod');
        Route::middleware([ResourceLimit::FilePull->middleware()])
            ->post('/pull', [Client\Servers\FileController::class, 'pull'])->name('pull');
        Route::get('/upload', Client\Servers\FileUploadController::class)->name('upload');
    });

    Route::prefix('/schedules')->group(function () {
        Route::get('/', [Client\Servers\ScheduleController::class, 'index']);
        Route::middleware([ResourceLimit::ScheduleCreate->middleware()])
            ->post('/', [Client\Servers\ScheduleController::class, 'store']);
        Route::get('/{schedule}', [Client\Servers\ScheduleController::class, 'view']);
        Route::post('/{schedule}', [Client\Servers\ScheduleController::class, 'update']);
        Route::post('/{schedule}/execute', [Client\Servers\ScheduleController::class, 'execute']);
        Route::delete('/{schedule}', [Client\Servers\ScheduleController::class, 'delete']);

        Route::post('/{schedule}/tasks', [Client\Servers\ScheduleTaskController::class, 'store']);
        Route::post('/{schedule}/tasks/{task}', [Client\Servers\ScheduleTaskController::class, 'update']);
        Route::delete('/{schedule}/tasks/{task}', [Client\Servers\ScheduleTaskController::class, 'delete']);
    });

    Route::prefix('/network/allocations')->group(function () {
        Route::get('/', [Client\Servers\NetworkAllocationController::class, 'index']);
        Route::middleware([ResourceLimit::AllocationCreate->middleware()])
            ->post('/', [Client\Servers\NetworkAllocationController::class, 'store']);
        Route::post('/{allocation}', [Client\Servers\NetworkAllocationController::class, 'update']);
        Route::post('/{allocation}/primary', [Client\Servers\NetworkAllocationController::class, 'setPrimary']);
        Route::delete('/{allocation}', [Client\Servers\NetworkAllocationController::class, 'delete']);
    });

    Route::prefix('/users')->group(function () {
        Route::get('/', [Client\Servers\SubuserController::class, 'index']);
        Route::middleware([ResourceLimit::SubuserCreate->middleware()])
            ->post('/', [Client\Servers\SubuserController::class, 'store']);
        Route::get('/{user:uuid}', [Client\Servers\SubuserController::class, 'view']);
        Route::post('/{user:uuid}', [Client\Servers\SubuserController::class, 'update']);
        Route::delete('/{user:uuid}', [Client\Servers\SubuserController::class, 'delete']);
    });

    Route::prefix('/backups')->group(function () {
        Route::get('/', [Client\Servers\BackupController::class, 'index']);
        Route::post('/', [Client\Servers\BackupController::class, 'store']);
        Route::get('/{backup:uuid}', [Client\Servers\BackupController::class, 'view']);
        Route::get('/{backup:uuid}/download', [Client\Servers\BackupController::class, 'download']);
        Route::put('/{backup:uuid}/rename', [Client\Servers\BackupController::class, 'rename']);
        Route::post('/{backup:uuid}/lock', [Client\Servers\BackupController::class, 'toggleLock']);
        Route::middleware([ResourceLimit::BackupRestore->middleware()])
            ->post('/{backup:uuid}/restore', [Client\Servers\BackupController::class, 'restore']);
        Route::delete('/{backup:uuid}', [Client\Servers\BackupController::class, 'delete']);
    });

    Route::prefix('/startup')->group(function () {
        Route::get('/', [Client\Servers\StartupController::class, 'index']);
        Route::put('/variable', [Client\Servers\StartupController::class, 'update']);
    });

    Route::prefix('/settings')->group(function () {
        Route::post('/rename', [Client\Servers\SettingsController::class, 'rename']);
        Route::post('/description', [Client\Servers\SettingsController::class, 'description']);
        Route::post('/reinstall', [Client\Servers\SettingsController::class, 'reinstall']);
        Route::put('/docker-image', [Client\Servers\SettingsController::class, 'dockerImage']);
    });
});
