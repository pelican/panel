<?php

namespace App\Http\Controllers\Api\Application\Nodes;

use App\Data\Api\Application\AllocationData;
use App\Exceptions\DisplayException;
use App\Exceptions\Service\Allocation\CidrOutOfRangeException;
use App\Exceptions\Service\Allocation\InvalidPortMappingException;
use App\Exceptions\Service\Allocation\PortOutOfRangeException;
use App\Exceptions\Service\Allocation\TooManyPortsInRangeException;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Allocations\DeleteAllocationRequest;
use App\Http\Requests\Api\Application\Allocations\GetAllocationsRequest;
use App\Http\Requests\Api\Application\Allocations\StoreAllocationRequest;
use App\Models\Allocation;
use App\Models\Node;
use App\Services\Allocations\AssignmentService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Node - Allocation')]
class AllocationController extends ApplicationApiController
{
    /**
     * AllocationController constructor.
     */
    public function __construct(
        private AssignmentService $assignmentService,
    ) {
        parent::__construct();
    }

    /**
     * List allocations
     *
     * Return all the allocations that exist for a given node.
     *
     * @return array<mixed>
     */
    public function index(GetAllocationsRequest $request, Node $node): array
    {
        $allocations = QueryBuilder::for($node->allocations())
            ->allowedFilters([
                AllowedFilter::exact('ip'),
                AllowedFilter::exact('port'),
                'ip_alias',
                AllowedFilter::callback('server_id', function (Builder $builder, $value) {
                    if (empty($value) || is_bool($value) || !ctype_digit((string) $value)) {
                        return $builder->whereNull('server_id');
                    }

                    return $builder->where('server_id', $value);
                }),
            ])
            ->paginate($request->query('per_page') ?? 50);

        return $this->response->collection($allocations)
            ->transformWith(AllocationData::class)
            ->toArray();
    }

    /**
     * Create allocation
     *
     * Store new allocations for a given node.
     *
     * @throws DisplayException
     * @throws CidrOutOfRangeException
     * @throws InvalidPortMappingException
     * @throws PortOutOfRangeException
     * @throws TooManyPortsInRangeException
     */
    public function store(StoreAllocationRequest $request, Node $node): JsonResponse
    {
        $this->assignmentService->handle($node, $request->validated());

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Delete allocation
     *
     * Delete a specific allocation from the Panel.
     */
    public function delete(DeleteAllocationRequest $request, Node $node, Allocation $allocation): JsonResponse
    {
        $allocation->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
