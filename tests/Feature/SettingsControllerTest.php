<?php

use App\Enums\ServerState;
use App\Enums\SubuserPermission;
use App\Http\Controllers\Api\Client\Servers\SettingsController;
use App\Models\ActivityLog;
use App\Repositories\Daemon\DaemonServerRepository;
use Symfony\Component\HttpFoundation\Response;

pest()->group('API');

covers(SettingsController::class);

it('server name cannot be changed', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::WebsocketConnect]);
    $originalName = $server->name;

    $this->actingAs($user)
        ->post("/api/client/servers/$server->uuid/settings/rename", [
            'name' => 'Test Server Name',
        ])
        ->assertStatus(Response::HTTP_FORBIDDEN);

    $server = $server->refresh();
    expect()->toLogActivities(0)
        ->and($server->name)->toBe($originalName);
});

it('server description can be changed', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::SettingsDescription]);
    $originalDescription = $server->description;

    $newDescription = 'Test Server Description';
    $this->actingAs($user)
        ->post("/api/client/servers/$server->uuid/settings/description", [
            'description' => $newDescription,
        ])
        ->assertStatus(Response::HTTP_NO_CONTENT);

    $server = $server->refresh();
    $logged = ActivityLog::first();
    expect()->toLogActivities(1)
        ->and($logged->properties['old'])->toBe($originalDescription)
        ->and($logged->properties['new'])->toBe($newDescription)
        ->and($server->description)->toBe($newDescription);
});

it('server description cannot be changed', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::SettingsDescription]);
    Config::set('panel.editable_server_descriptions', false);
    $originalDescription = $server->description;

    $this->actingAs($user)
        ->post("/api/client/servers/$server->uuid/settings/description", [
            'description' => 'Test Description',
        ])
        ->assertStatus(Response::HTTP_FORBIDDEN);

    $server = $server->refresh();
    expect()->toLogActivities(0)
        ->and($server->description)->toBe($originalDescription);
});

it('server name can be changed', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::WebsocketConnect, SubuserPermission::SettingsRename]);
    $originalName = $server->name;

    $this->actingAs($user)
        ->post("/api/client/servers/$server->uuid/settings/rename", [
            'name' => 'Test Server Name',
        ])
        ->assertStatus(Response::HTTP_NO_CONTENT);

    $server = $server->refresh();
    expect()->toLogActivities(1)
        ->and($server->name)->not()->toBe($originalName);
});

test('unauthorized user cannot change docker image in use by server', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::WebsocketConnect]);
    $originalImage = $server->image;

    $this->actingAs($user)
        ->put("/api/client/servers/$server->uuid/settings/docker-image", [
            'docker_image' => 'ghcr.io/pelican-eggs/yolks:java_21',
        ])
        ->assertStatus(Response::HTTP_FORBIDDEN);

    $server = $server->refresh();
    expect()->toLogActivities(0)
        ->and($server->image)->toBe($originalImage);
});

test('cannot change docker image to image not allowed by egg', function () {

    [$user, $server] = generateTestAccount([SubuserPermission::StartupDockerImage]);
    $server->image = 'ghcr.io/pelican-eggs/yolks:java_17';
    $server->save();

    $newImage = 'ghcr.io/pelican-eggs/fake:image';

    $server = $server->refresh();

    $this->actingAs($user)
        ->putJson("/api/client/servers/$server->uuid/settings/docker-image", [
            'docker_image' => $newImage,
        ])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

    $server->refresh();
    expect()->toLogActivities(0)
        ->and($server->image)->not()->toBe($newImage);
});

test('can change docker image in use by server', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::StartupDockerImage]);
    $oldImage = 'ghcr.io/pelican-eggs/yolks:java_17';
    $server->image = $oldImage;
    $server->save();

    $newImage = 'ghcr.io/pelican-eggs/yolks:java_21';

    $this->actingAs($user)
        ->putJson("/api/client/servers/$server->uuid/settings/docker-image", [
            'docker_image' => $newImage,
        ])
        ->assertStatus(Response::HTTP_NO_CONTENT);

    $server = $server->refresh();

    $logItem = ActivityLog::first();
    expect()->toLogActivities(1)
        ->and($logItem->properties['old'])->toBe($oldImage)
        ->and($logItem->properties['new'])->toBe($newImage)
        ->and($server->image)->toBe($newImage);
});

test('unable to change the docker image set by administrator', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::StartupDockerImage]);
    $oldImage = 'ghcr.io/pelican-eggs/yolks:java_custom';
    $server->image = $oldImage;
    $server->save();

    $newImage = 'ghcr.io/pelican-eggs/yolks:java_8';

    $this->actingAs($user)
        ->putJson("/api/client/servers/$server->uuid/settings/docker-image", [
            'docker_image' => $newImage,
        ])
        ->assertStatus(Response::HTTP_BAD_REQUEST);

    $server = $server->refresh();

    expect()->toLogActivities(0)
        ->and($server->image)->toBe($oldImage);
});

test('can be reinstalled', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::SettingsReinstall]);
    expect($server->isInstalled())->toBeTrue();

    $service = Mockery::mock(DaemonServerRepository::class);
    $this->app->instance(DaemonServerRepository::class, $service);

    $service->expects('setServer')
        ->with(Mockery::on(function ($value) use ($server) {
            return $value->uuid === $server->uuid;
        }))
        ->andReturnSelf()
        ->getMock()
        ->expects('reinstall')
        ->andReturnUndefined();

    $this->actingAs($user)->postJson("/api/client/servers/$server->uuid/settings/reinstall")
        ->assertStatus(Response::HTTP_ACCEPTED);

    $server = $server->refresh();
    expect()->toLogActivities(1)
        ->and($server->status)->toBe(ServerState::Installing);
});
