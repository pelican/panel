<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Exceptions\DisplayException;
use App\Exceptions\Model\DataValidationException;
use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\Network\DeleteAllocationRequest;
use App\Http\Requests\Api\Client\Servers\Network\GetNetworkRequest;
use App\Http\Requests\Api\Client\Servers\Network\NewAllocationRequest;
use App\Http\Requests\Api\Client\Servers\Network\SetPrimaryAllocationRequest;
use App\Http\Requests\Api\Client\Servers\Network\UpdateAllocationRequest;
use App\Models\Allocation;
use App\Models\Server;
use App\Services\Allocations\FindAssignableAllocationService;
use App\Transformers\Api\Client\AllocationTransformer;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Server - Allocation')]
class NetworkAllocationController extends ClientApiController
{
    /**
     * NetworkAllocationController constructor.
     */
    public function __construct(
        private FindAssignableAllocationService $assignableAllocationService,
    ) {
        parent::__construct();
    }

    /**
     * List allocations
     *
     * Lists all the allocations available to a server and whether
     * they are currently assigned as the primary for this server.
     *
     * @return array<array-key, mixed>
     */
    public function index(GetNetworkRequest $request, Server $server): array
    {
        return $this->fractal->collection($server->allocations)
            ->transformWith($this->getTransformer(AllocationTransformer::class))
            ->toArray();
    }

    /**
     * Update allocation
     *
     * Set the primary allocation for a server.
     *
     * @return array<array-key, mixed>
     *
     * @throws DataValidationException
     */
    public function update(UpdateAllocationRequest $request, Server $server, Allocation $allocation): array
    {
        $original = $allocation->notes;

        $allocation->forceFill(['notes' => $request->input('notes')])->save();

        if ($original !== $allocation->notes) {
            Activity::event('server:allocation.notes')
                ->subject($allocation)
                ->property(['allocation' => $allocation->address, 'old' => $original, 'new' => $allocation->notes])
                ->log();
        }

        return $this->fractal->item($allocation)
            ->transformWith($this->getTransformer(AllocationTransformer::class))
            ->toArray();
    }

    /**
     * Set primary allocation
     *
     * Set the primary allocation for a server.
     *
     * @return array<array-key, mixed>
     *
     * @throws DataValidationException
     */
    public function setPrimary(SetPrimaryAllocationRequest $request, Server $server, Allocation $allocation): array
    {
        $server->allocation()->associate($allocation);
        $server->save();

        Activity::event('server:allocation.primary')
            ->subject($allocation)
            ->property('allocation', $allocation->address)
            ->log();

        return $this->fractal->item($allocation)
            ->transformWith($this->getTransformer(AllocationTransformer::class))
            ->toArray();
    }

    /**
     * Create allocation
     *
     * Set the notes for the allocation for a server.
     *
     * @return array<array-key, mixed>
     *
     * @throws DisplayException
     */
    public function store(NewAllocationRequest $request, Server $server): array
    {
        $allocation = Activity::event('server:allocation.create')->transaction(function ($log) use ($server) {
            $server->allocations()->lockForUpdate();

            throw_if($server->allocations->count() >= $server->allocation_limit, new DisplayException('Cannot assign additional allocations to this server: limit has been reached.'));

            $allocation = $this->assignableAllocationService->handle($server);

            $log->subject($allocation)->property('allocation', $allocation->address);

            return $allocation;
        });

        return $this->fractal->item($allocation)
            ->transformWith($this->getTransformer(AllocationTransformer::class))
            ->toArray();
    }

    /**
     * Delete allocation
     *
     * Delete an allocation from a server.
     *
     * @throws DisplayException
     */
    public function delete(DeleteAllocationRequest $request, Server $server, Allocation $allocation): JsonResponse
    {
        // Don't allow the deletion of allocations if the server does not have an
        // allocation limit set.
        throw_if(empty($server->allocation_limit), new DisplayException('You cannot delete allocations for this server: no allocation limit is set.'));

        Allocation::query()->where('id', $allocation->id)->update(Allocation::RELEASE_ATTRIBUTES);

        Activity::event('server:allocation.delete')
            ->subject($allocation)
            ->property('allocation', $allocation->address)
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
