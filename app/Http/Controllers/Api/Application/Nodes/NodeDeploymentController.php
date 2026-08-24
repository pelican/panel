<?php

namespace App\Http\Controllers\Api\Application\Nodes;

use App\Data\Api\Application\NodeData;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Nodes\GetDeployableNodesRequest;
use App\Services\Deployment\FindViableNodesService;
use Dedoc\Scramble\Attributes\Group;

#[Group('Node', weight: 2)]
class NodeDeploymentController extends ApplicationApiController
{
    public function __construct(private FindViableNodesService $viableNodesService)
    {
        parent::__construct();
    }

    /**
     * Get deployable nodes
     *
     * Finds any nodes that are available using the given deployment criteria. This works
     * similarly to the server creation process, but allows you to pass the deployment object
     * to this endpoint and get back a list of all Nodes satisfying the requirements.
     *
     * @return array<mixed>
     */
    public function __invoke(GetDeployableNodesRequest $request): array
    {
        $data = $request->validated();

        $nodes = $this->viableNodesService->handle(
            $data['memory'] ?? 0,
            $data['disk'] ?? 0,
            $data['cpu'] ?? 0,
            $data['tags'] ?? [],
        );

        return $this->response->collection($nodes)
            ->transformWith(NodeData::class)
            ->toArray();
    }
}
