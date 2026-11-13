<?php

namespace App\Http\Requests\Api\Application\Mounts;

use App\Models\Egg;

class GetMountEggsRequest extends GetMountRelationRequest
{
    protected string $relatedResource = Egg::RESOURCE_NAME;
}
