<?php

namespace App\Http\Controllers\Api\Application\Nodes;

use App\Data\Api\Application\NodeData;
use App\Exceptions\Model\DataValidationException;
use App\Exceptions\Service\HasActiveServersException;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Nodes\DeleteNodeRequest;
use App\Http\Requests\Api\Application\Nodes\GetNodeRequest;
use App\Http\Requests\Api\Application\Nodes\GetNodesRequest;
use App\Http\Requests\Api\Application\Nodes\StoreNodeRequest;
use App\Http\Requests\Api\Application\Nodes\UpdateNodeRequest;
use App\Models\Node;
use App\Services\Nodes\NodeDeletionService;
use App\Services\Nodes\NodeUpdateService;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

#[Group('Node', weight: 0)]
class NodeController extends ApplicationApiController
{
    /**
     * NodeController constructor.
     */
    public function __construct(
        private NodeDeletionService $deletionService,
        private NodeUpdateService $updateService
    ) {
        parent::__construct();
    }

    /**
     * List nodes
     *
     * Return all the nodes currently available on the Panel.
     *
     * @return array<mixed>
     */
    public function index(GetNodesRequest $request): array
    {
        $nodes = QueryBuilder::for(Node::class)
            ->allowedFilters(['uuid', 'name', 'fqdn', 'daemon_token_id'])
            ->allowedSorts(['id', 'uuid', 'memory', 'disk', 'cpu'])
            ->paginate($request->query('per_page') ?? 50);

        return $this->response->collection($nodes)
            ->transformWith(NodeData::class)
            ->toArray();
    }

    /**
     * View node
     *
     * Return data for a single instance of a node.
     *
     * @return array<mixed>
     */
    public function view(GetNodeRequest $request, Node $node): array
    {
        return $this->response->item($node)
            ->transformWith(NodeData::class)
            ->toArray();
    }

    /**
     * Create node
     *
     * Create a new node on the Panel. Returns the created node and an HTTP/201
     * status response on success.
     *
     * @throws DataValidationException
     */
    public function store(StoreNodeRequest $request): JsonResponse
    {
        $node = Node::create($request->validated());

        return $this->response->item($node)
            ->transformWith(NodeData::class)
            ->addMeta([
                'resource' => route('api.application.nodes.view', [
                    'node' => $node->id,
                ]),
            ])
            ->respond(201);
    }

    /**
     * Update node
     *
     * Update an existing node on the Panel.
     *
     * @return array<mixed>
     *
     * @throws Throwable
     */
    public function update(UpdateNodeRequest $request, Node $node): array
    {
        try {
            $node = $this->updateService->handle(
                $node,
                $request->validated(),
                $request->input('reset_secret') === true
            );
        } catch (Exception $exception) {
            report($exception);
        }

        return $this->response->item($node)
            ->transformWith(NodeData::class)
            ->toArray();
    }

    /**
     * Delete node
     *
     * Deletes a given node from the Panel as long as there are no servers
     * currently attached to it.
     *
     * @throws HasActiveServersException
     */
    public function delete(DeleteNodeRequest $request, Node $node): JsonResponse
    {
        $this->deletionService->handle($node);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
