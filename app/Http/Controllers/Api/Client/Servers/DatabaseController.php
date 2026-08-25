<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Data\Api\Client\DatabaseData;
use App\Exceptions\Service\Database\DatabaseClientFeatureNotEnabledException;
use App\Exceptions\Service\Database\TooManyDatabasesException;
use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\Databases\DeleteDatabaseRequest;
use App\Http\Requests\Api\Client\Servers\Databases\GetDatabasesRequest;
use App\Http\Requests\Api\Client\Servers\Databases\RotatePasswordRequest;
use App\Http\Requests\Api\Client\Servers\Databases\StoreDatabaseRequest;
use App\Models\Database;
use App\Models\Server;
use App\Services\Databases\DatabaseManagementService;
use App\Services\Databases\DeployServerDatabaseService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Response;
use Throwable;

#[Group('Server - Database')]
class DatabaseController extends ClientApiController
{
    /**
     * DatabaseController constructor.
     */
    public function __construct(
        private DeployServerDatabaseService $deployDatabaseService,
        private DatabaseManagementService $managementService,
    ) {
        parent::__construct();
    }

    /**
     * List databases
     *
     * Return all the databases that belong to the given server.
     *
     * @return array<string, mixed>
     */
    public function index(GetDatabasesRequest $request, Server $server): array
    {
        return $this->response->collection($server->databases)
            ->transformWith(DatabaseData::class)
            ->toArray();
    }

    /**
     * Create database
     *
     * Create a new database for the given server and return it.
     *
     * @return array<string, mixed>
     *
     * @throws Throwable
     * @throws TooManyDatabasesException
     * @throws DatabaseClientFeatureNotEnabledException
     */
    public function store(StoreDatabaseRequest $request, Server $server): array
    {
        $database = Activity::event('server:database.create')->transaction(function ($log) use ($request, $server) {
            $server->databases()->lockForUpdate()->count();

            $database = $this->deployDatabaseService->handle($server, $request->validated());

            $log->subject($database)->property('name', $database->database);

            return $database;
        });

        return $this->response->item($database)
            ->parseIncludes(['password'])
            ->transformWith(DatabaseData::class)
            ->toArray();
    }

    /**
     * Rotate password
     *
     * Rotates the password for the given server model and returns a fresh instance to
     * the caller.
     *
     * @return array<array-key, mixed>
     *
     * @throws Throwable
     */
    public function rotatePassword(RotatePasswordRequest $request, Server $server, Database $database): array
    {
        Activity::event('server:database.rotate-password')
            ->subject($database)
            ->property('name', $database->database)
            ->transaction(fn () => $this->managementService->rotatePassword($database));

        return $this->response->item($database->refresh())
            ->parseIncludes(['password'])
            ->transformWith(DatabaseData::class)
            ->toArray();
    }

    /**
     * Delete database
     *
     * Removes a database from the server.
     */
    public function delete(DeleteDatabaseRequest $request, Server $server, Database $database): Response
    {
        $this->managementService->delete($database);

        Activity::event('server:database.delete')
            ->subject($database)
            ->property('name', $database->database)
            ->log();

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
