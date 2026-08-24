<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Data\Api\Client\ServerData;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\GetServerRequest;
use App\Models\Server;
use App\Services\Servers\GetUserPermissionsService;
use Dedoc\Scramble\Attributes\Group;

#[Group('Server', weight: 0)]
class ServerController extends ClientApiController
{
    public function __construct(private GetUserPermissionsService $permissionsService)
    {
        parent::__construct();
    }

    /**
     * View server
     *
     * Transform an individual server into a response that can be consumed by a client using the API.
     *
     * @return array<array-key, mixed>
     */
    public function index(GetServerRequest $request, Server $server): array
    {
        return $this->response->item($server)
            ->transformWith(ServerData::class)
            ->addMeta([
                'is_server_owner' => $request->user()->id === $server->owner_id,
                'user_permissions' => $this->permissionsService->handle($server, $request->user()),
            ])
            ->toArray();
    }
}
