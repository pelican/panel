<?php

namespace App\Http\Requests\Api\Application\Mounts;

use App\Models\Node;

class GetMountNodesRequest extends GetMountRelationRequest
{
    protected string $relatedResource = Node::RESOURCE_NAME;
}
