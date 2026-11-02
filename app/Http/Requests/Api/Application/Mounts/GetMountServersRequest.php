<?php

namespace App\Http\Requests\Api\Application\Mounts;

use App\Models\Server;

class GetMountServersRequest extends GetMountRelationRequest
{
    protected string $relatedResource = Server::RESOURCE_NAME;
}
