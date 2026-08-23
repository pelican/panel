<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\Settings\DescriptionServerRequest;
use App\Http\Requests\Api\Client\Servers\Settings\ReinstallServerRequest;
use App\Http\Requests\Api\Client\Servers\Settings\RenameServerRequest;
use App\Http\Requests\Api\Client\Servers\Settings\SetDockerImageRequest;
use App\Models\Server;
use App\Services\Servers\ReinstallServerService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

#[Group('Server - Settings')]
class SettingsController extends ClientApiController
{
    /**
     * SettingsController constructor.
     */
    public function __construct(
        private ReinstallServerService $reinstallServerService
    ) {
        parent::__construct();
    }

    /**
     * Rename
     *
     * Renames a server.
     */
    public function rename(RenameServerRequest $request, Server $server): JsonResponse
    {
        $originalName = $server->name;
        $name = $request->input('name');

        $server->update(['name' => $name]);

        if ($server->wasChanged('name')) {
            Activity::event('server:settings.rename')
                ->property(['old' => $originalName, 'new' => $name])
                ->log();
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Update server description
     *
     * Change the description shown for the server in the Panel. This is cosmetic and does not
     * affect how the server runs.
     */
    public function description(DescriptionServerRequest $request, Server $server): JsonResponse
    {
        if (!config('panel.editable_server_descriptions')) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $originalDescription = $server->description;
        $description = $request->input('description');
        $server->update(['description' => $description ?? '']);

        if ($server->wasChanged('description')) {
            Activity::event('server:settings.description')
                ->property(['old' => $originalDescription, 'new' => $description])
                ->log();
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Reinstall
     *
     * Reinstalls the server on the daemon.
     *
     * @throws Throwable
     */
    public function reinstall(ReinstallServerRequest $request, Server $server): JsonResponse
    {
        $this->reinstallServerService->handle($server);

        Activity::event('server:reinstall')->log();

        return new JsonResponse([], Response::HTTP_ACCEPTED);
    }

    /**
     * Change docker image
     *
     * Changes the Docker image in use by the server.
     *
     * @throws Throwable
     */
    public function dockerImage(SetDockerImageRequest $request, Server $server): JsonResponse
    {
        throw_unless(in_array($server->image, $server->egg->docker_images), new BadRequestHttpException('This server\'s Docker image has been manually set by an administrator and cannot be updated.'));

        $original = $server->image;
        $server->forceFill(['image' => $request->input('docker_image')])->saveOrFail();

        if ($original !== $server->image) {
            Activity::event('server:startup.image')
                ->property(['old' => $original, 'new' => $request->input('docker_image')])
                ->log();
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
