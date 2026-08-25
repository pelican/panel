<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Data\Api\Client\SubuserData;
use App\Enums\SubuserPermission;
use App\Exceptions\Model\DataValidationException;
use App\Exceptions\Service\Subuser\ServerSubuserExistsException;
use App\Exceptions\Service\Subuser\UserIsServerOwnerException;
use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\Subusers\DeleteSubuserRequest;
use App\Http\Requests\Api\Client\Servers\Subusers\GetSubuserRequest;
use App\Http\Requests\Api\Client\Servers\Subusers\StoreSubuserRequest;
use App\Http\Requests\Api\Client\Servers\Subusers\UpdateSubuserRequest;
use App\Models\Server;
use App\Models\Subuser;
use App\Models\User;
use App\Services\Subusers\SubuserCreationService;
use App\Services\Subusers\SubuserDeletionService;
use App\Services\Subusers\SubuserUpdateService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

#[Group('Server - Subuser')]
class SubuserController extends ClientApiController
{
    /**
     * SubuserController constructor.
     */
    public function __construct(
        private SubuserCreationService $creationService,
        private SubuserUpdateService $updateService,
        private SubuserDeletionService $deletionService
    ) {
        parent::__construct();
    }

    /**
     * List subusers
     *
     * Return the users associated with this server instance.
     *
     * @return array<array-key, mixed>
     */
    public function index(GetSubuserRequest $request, Server $server): array
    {
        return $this->response->collection($server->subusers)
            ->transformWith(SubuserData::class)
            ->toArray();
    }

    /**
     * View subusers
     *
     * Returns a single subuser associated with this server instance.
     *
     * @return array<array-key, mixed>
     */
    public function view(GetSubuserRequest $request, Server $server, User $user): array
    {
        $subuser = $request->attributes->get('subuser');

        return $this->response->item($subuser)
            ->transformWith(SubuserData::class)
            ->toArray();
    }

    /**
     * Create subuser
     *
     * Create a new subuser for the given server.
     *
     * @return array<array-key, mixed>
     *
     * @throws DataValidationException
     * @throws ServerSubuserExistsException
     * @throws UserIsServerOwnerException
     * @throws Throwable
     */
    public function store(StoreSubuserRequest $request, Server $server): array
    {
        $email = $request->input('email');
        $permissions = $this->getCleanedPermissions($request);

        $subuser = $this->creationService->handle($server, $email, $permissions);

        Activity::event('server:subuser.create')
            ->subject($subuser->user)
            ->property(['email' => $email, 'permissions' => $subuser->permissions])
            ->log();

        return $this->response->item($subuser)
            ->transformWith(SubuserData::class)
            ->toArray();
    }

    /**
     * Update subuser
     *
     * Update a given subuser in the system for the server.
     *
     * @return array<array-key, mixed>
     *
     * @throws DataValidationException
     */
    public function update(UpdateSubuserRequest $request, Server $server, User $user): array
    {
        /** @var Subuser $subuser */
        $subuser = $request->attributes->get('subuser');

        $this->updateService->handle($subuser, $server, $this->getCleanedPermissions($request));

        return $this->response->item($subuser->refresh())
            ->transformWith(SubuserData::class)
            ->toArray();
    }

    /**
     * Delete subuser
     *
     * Removes a subusers from a server's assignment.
     */
    public function delete(DeleteSubuserRequest $request, Server $server, User $user): JsonResponse
    {
        /** @var Subuser $subuser */
        $subuser = $request->attributes->get('subuser');

        $this->deletionService->handle($subuser, $server);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Returns the "cleaned" permissions for subusers and parses out any permissions
     * that were passed that do not also exist in the internally tracked list of
     * permissions.
     *
     * @return string[]
     */
    protected function getCleanedPermissions(Request $request): array
    {
        return collect($request->input('permissions') ?? [])
            ->intersect(Subuser::allPermissionKeys())
            ->push(SubuserPermission::WebsocketConnect->value)
            ->unique()
            ->values()
            ->toArray();
    }
}
