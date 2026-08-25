<?php

namespace App\Http\Controllers\Api\Application\Mounts;

use App\Data\Api\Application\EggData;
use App\Data\Api\Application\MountData;
use App\Data\Api\Application\NodeData;
use App\Data\Api\Application\ServerData;
use App\Exceptions\Model\DataValidationException;
use App\Exceptions\Service\HasActiveServersException;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Eggs\GetEggsRequest;
use App\Http\Requests\Api\Application\Mounts\DeleteMountRelationRequest;
use App\Http\Requests\Api\Application\Mounts\DeleteMountRequest;
use App\Http\Requests\Api\Application\Mounts\GetMountRequest;
use App\Http\Requests\Api\Application\Mounts\StoreMountRequest;
use App\Http\Requests\Api\Application\Mounts\UpdateMountEggsRequest;
use App\Http\Requests\Api\Application\Mounts\UpdateMountNodesRequest;
use App\Http\Requests\Api\Application\Mounts\UpdateMountRequest;
use App\Http\Requests\Api\Application\Mounts\UpdateMountServersRequest;
use App\Http\Requests\Api\Application\Nodes\GetNodesRequest;
use App\Http\Requests\Api\Application\Servers\GetServerRequest;
use App\Models\Mount;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

class MountController extends ApplicationApiController
{
    /**
     * List mounts
     *
     * Return all the mounts currently available on the Panel.
     *
     * @return array<array-key, mixed>
     */
    public function index(GetMountRequest $request): array
    {
        $mounts = QueryBuilder::for(Mount::class)
            ->allowedFilters(['uuid', 'name'])
            ->allowedSorts(['id', 'uuid'])
            ->paginate($request->query('per_page') ?? 50);

        return $this->response->collection($mounts)
            ->transformWith(MountData::class)
            ->toArray();
    }

    /**
     * View mount
     *
     * Return data for a single instance of a mount.
     *
     * @return array<array-key, mixed>
     */
    public function view(GetMountRequest $request, Mount $mount): array
    {
        return $this->response->item($mount)
            ->transformWith(MountData::class)
            ->toArray();
    }

    /**
     * Create mount
     *
     * Create a new mount on the Panel. Returns the created mount and an HTTP/201
     * status response on success.
     *
     * @throws DataValidationException
     */
    public function store(StoreMountRequest $request): JsonResponse
    {
        $model = (new Mount())->fill($request->validated());
        $model->forceFill(['uuid' => Uuid::uuid4()->toString()]);

        $model->saveOrFail();
        $mount = $model->fresh();

        return $this->response->item($mount)
            ->transformWith(MountData::class)
            ->addMeta([
                'resource' => route('api.application.mounts.view', [
                    'mount' => $mount->id,
                ]),
            ])
            ->respond(201);
    }

    /**
     * Update mount
     *
     * Update an existing mount on the Panel.
     *
     * @return array<array-key, mixed>
     *
     * @throws Throwable
     */
    public function update(UpdateMountRequest $request, Mount $mount): array
    {
        $mount->forceFill($request->validated())->save();

        return $this->response->item($mount)
            ->transformWith(MountData::class)
            ->toArray();
    }

    /**
     * Delete mount
     *
     * Deletes a given mount from the Panel as long as there are no servers
     * currently attached to it.
     *
     * @throws HasActiveServersException
     */
    public function delete(DeleteMountRequest $request, Mount $mount): JsonResponse
    {
        throw_if($mount->servers()->count() > 0, new HasActiveServersException(trans('exceptions.mount.servers_attached')));

        $mount->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * List assigned eggs
     *
     * Return the eggs the selected mount is assigned to. Servers may only use the mount when
     * their egg appears in this list.
     *
     * @return array<array-key, mixed>
     */
    public function getEggs(GetEggsRequest $request, Mount $mount): array
    {
        return $this->response->collection($mount->eggs)
            ->transformWith(EggData::class)
            ->toArray();
    }

    /**
     * List assigned nodes
     *
     * Return the nodes the selected mount is assigned to. The mount is only available to servers
     * that live on one of these nodes.
     *
     * @return array<array-key, mixed>
     */
    public function getNodes(GetNodesRequest $request, Mount $mount): array
    {
        return $this->response->collection($mount->nodes)
            ->transformWith(NodeData::class)
            ->toArray();
    }

    /**
     * List assigned servers
     *
     * Return the servers that currently have the selected mount attached.
     *
     * @return array<array-key, mixed>
     */
    public function getServers(GetServerRequest $request, Mount $mount): array
    {
        return $this->response->collection($mount->servers)
            ->transformWith(ServerData::class)
            ->toArray();
    }

    /**
     * Assign eggs to mount
     *
     * Adds eggs to the mount's many-to-many relation.
     *
     * @return array<array-key, mixed>
     */
    public function addEggs(UpdateMountEggsRequest $request, Mount $mount): array
    {
        $mount->eggs()->attach($request->validated('eggs'));

        return $this->response->item($mount)
            ->transformWith(MountData::class)
            ->toArray();
    }

    /**
     * Assign nodes to mount
     *
     * Adds nodes to the mount's many-to-many relation.
     *
     * @return array<array-key, mixed>
     */
    public function addNodes(UpdateMountNodesRequest $request, Mount $mount): array
    {
        $mount->nodes()->attach($request->validated('nodes'));

        return $this->response->item($mount)
            ->transformWith(MountData::class)
            ->toArray();
    }

    /**
     * Assign servers to mount
     *
     * Adds servers to the mount's many-to-many relation.
     *
     * @return array<array-key, mixed>
     */
    public function addServers(UpdateMountServersRequest $request, Mount $mount): array
    {
        $mount->servers()->attach($request->validated('servers'));

        return $this->response->item($mount)
            ->transformWith(MountData::class)
            ->toArray();
    }

    /**
     * Unassign egg from mount
     *
     * Deletes an egg from the mount's many-to-many relation.
     */
    public function deleteEgg(DeleteMountRelationRequest $request, Mount $mount, int $egg_id): JsonResponse
    {
        $mount->eggs()->detach($egg_id);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Unassign node from mount
     *
     * Deletes a node from the mount's many-to-many relation.
     */
    public function deleteNode(DeleteMountRelationRequest $request, Mount $mount, int $node_id): JsonResponse
    {
        $mount->nodes()->detach($node_id);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Unassign server from mount
     *
     * Deletes a server from the mount's many-to-many relation.
     */
    public function deleteServer(DeleteMountRelationRequest $request, Mount $mount, int $server_id): JsonResponse
    {
        $mount->servers()->detach($server_id);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
