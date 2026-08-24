<?php

namespace App\Http\Controllers\Api\Application\Servers;

use App\Data\Api\Application\ServerData;
use App\Exceptions\DisplayException;
use App\Exceptions\Model\DataValidationException;
use App\Exceptions\Service\Deployment\NoViableAllocationException;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Servers\GetServerRequest;
use App\Http\Requests\Api\Application\Servers\GetServersRequest;
use App\Http\Requests\Api\Application\Servers\ServerWriteRequest;
use App\Http\Requests\Api\Application\Servers\StoreServerRequest;
use App\Models\Server;
use App\Services\Servers\ServerCreationService;
use App\Services\Servers\ServerDeletionService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

#[Group('Server', weight: 0)]
class ServerController extends ApplicationApiController
{
    /**
     * ServerController constructor.
     */
    public function __construct(
        private ServerCreationService $creationService,
        private ServerDeletionService $deletionService
    ) {
        parent::__construct();
    }

    /**
     * List servers
     *
     * Return all the servers that currently exist on the Panel.
     *
     * @return array<array-key, mixed>
     */
    public function index(GetServersRequest $request): array
    {
        $servers = QueryBuilder::for(Server::class)
            ->allowedFilters(['uuid', 'uuid_short', 'name', 'description', 'image', 'external_id'])
            ->allowedSorts(['id', 'uuid'])
            ->paginate($request->query('per_page') ?? 50);

        return $this->response->collection($servers)
            ->transformWith(ServerData::class)
            ->toArray();
    }

    /**
     * Create server
     *
     * Create a new server on the system.
     *
     * @throws Throwable
     * @throws ValidationException
     * @throws DisplayException
     * @throws DataValidationException
     * @throws NoViableAllocationException
     */
    public function store(StoreServerRequest $request): JsonResponse
    {
        $server = $this->creationService->handle($request->validated(), $request->getDeploymentObject());

        return $this->response->item($server)
            ->transformWith(ServerData::class)
            ->respond(201);
    }

    /**
     * View server
     *
     * Show a single server transformed for the application API.
     *
     * @return array<array-key, mixed>
     */
    public function view(GetServerRequest $request, Server $server): array
    {
        return $this->response->item($server)
            ->transformWith(ServerData::class)
            ->toArray();
    }

    /**
     * Delete server
     *
     * Deletes a server.
     *
     * @throws DisplayException
     */
    public function delete(ServerWriteRequest $request, Server $server, string $force = ''): Response
    {
        $this->deletionService->withForce($force === 'force')->handle($server);

        return $this->returnNoContent();
    }
}
