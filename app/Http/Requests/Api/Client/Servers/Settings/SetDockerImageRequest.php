<?php

namespace App\Http\Requests\Api\Client\Servers\Settings;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\Server;
use Illuminate\Validation\Rule;
use Webmozart\Assert\Assert;

class SetDockerImageRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::StartupDockerImage;
    }

    public function rules(): array
    {
        /** @var Server $server */
        $server = $this->route()->parameter('server');

        Assert::isInstanceOf($server, Server::class);

        return [
            /** Docker image the server container is built from. Must be one of the images the server's egg offers. */
            'docker_image' => ['required', 'string', Rule::in(array_values($server->egg->docker_images))],
        ];
    }
}
