<?php

namespace App\Http\Controllers\Api\Application\Servers;

use App\Data\Api\Application\ServerDatabaseData;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Servers\Databases\GetServerDatabaseRequest;
use App\Http\Requests\Api\Application\Servers\Databases\GetServerDatabasesRequest;
use App\Http\Requests\Api\Application\Servers\Databases\ServerDatabaseWriteRequest;
use App\Http\Requests\Api\Application\Servers\Databases\StoreServerDatabaseRequest;
use App\Models\Database;
use App\Models\Server;
use App\Services\Databases\DatabaseManagementService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;

#[Group('Server - Database')]
class DatabaseController extends ApplicationApiController
{
    /**
     * DatabaseController constructor.
     */
    public function __construct(
        private DatabaseManagementService $databaseManagementService,
    ) {
        parent::__construct();
    }

    /**
     * List databases
     *
     * Return a listing of all databases currently available to a single server.
     *
     * @return array<array-key, mixed>
     */
    public function index(GetServerDatabasesRequest $request, Server $server): array
    {
        return $this->response->collection($server->databases)
            ->transformWith(ServerDatabaseData::class)
            ->toArray();
    }

    /**
     * View database
     *
     * Return a single server database.
     *
     * @return array<array-key, mixed>
     */
    public function view(GetServerDatabaseRequest $request, Server $server, Database $database): array
    {
        return $this->response->item($database)
            ->transformWith(ServerDatabaseData::class)
            ->toArray();
    }

    /**
     * Reset password
     *
     * Reset the password for a specific server database.
     *
     * @throws Throwable
     */
    public function resetPassword(ServerDatabaseWriteRequest $request, Server $server, Database $database): JsonResponse
    {
        $this->databaseManagementService->rotatePassword($database);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Create database
     *
     * Create a new database on the Panel for a given server.
     *
     * @throws Throwable
     */
    public function store(StoreServerDatabaseRequest $request, Server $server): JsonResponse
    {
        $database = $this->databaseManagementService->create($server, array_merge($request->validated(), [
            'database' => $request->databaseName(),
        ]));

        return $this->response->item($database)
            ->transformWith(ServerDatabaseData::class)
            ->addMeta([
                'resource' => route('api.application.servers.databases.view', [
                    'server' => $server->id,
                    'database' => $database->id,
                ]),
            ])
            ->respond(Response::HTTP_CREATED);
    }

    /**
     * Delete database
     *
     * Handle a request to delete a specific server database from the Panel.
     */
    public function delete(ServerDatabaseWriteRequest $request, Server $server, Database $database): Response
    {
        $this->databaseManagementService->delete($database);

        return response('', 204);
    }
}
