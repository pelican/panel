<?php

namespace App\Http\Requests\Api\Application\Mounts;

use App\Services\Acl\Api\AdminAcl;

/**
 * Lists the models a mount is attached to, which needs read access to both the mount
 * and the related resource being returned.
 */
abstract class GetMountRelationRequest extends GetMountRequest
{
    protected string $relatedResource;

    public function authorize(): bool
    {
        return parent::authorize() && $this->canAccess($this->relatedResource, AdminAcl::READ);
    }
}
