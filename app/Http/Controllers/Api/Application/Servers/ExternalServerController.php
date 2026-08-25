<?php

namespace App\Http\Controllers\Api\Application\Servers;

use App\Data\Api\Application\ServerData;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Servers\GetExternalServerRequest;
use App\Models\Server;
use Dedoc\Scramble\Attributes\Group;

#[Group('Server', weight: 1)]
class ExternalServerController extends ApplicationApiController
{
    /**
     * View server (external id)
     *
     * Retrieve a specific server from the database using its external ID.
     *
     * @return array<array-key, mixed>
     */
    public function index(GetExternalServerRequest $request, string $external_id): array
    {
        $server = Server::query()->where('external_id', $external_id)->firstOrFail();

        return $this->response->item($server)
            ->transformWith(ServerData::class)
            ->toArray();
    }
}
