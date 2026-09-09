<?php

namespace App\Http\Requests\Api\Application\Mounts;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\Mount;
use App\Services\Acl\Api\AdminAcl;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;

// The rules come straight off the model, so there is no rules array to hang comments on.
// Attributes are not inherited, so UpdateMountRequest repeats them against the same descriptions.
#[BodyParameter('name', description: 'Name the mount is shown under in the Panel.')]
#[BodyParameter('description', description: 'Free form text describing the mount.')]
#[BodyParameter('source', description: 'Absolute path on the node that is mounted into the server.')]
#[BodyParameter('target', description: 'Absolute path inside the server container the source appears at.')]
#[BodyParameter('read_only', description: 'Mount the source read only so servers cannot write to it.')]
#[BodyParameter('user_mountable', description: 'Whether server owners may attach this mount themselves.')]
class StoreMountRequest extends ApplicationApiRequest
{
    protected ?string $resource = Mount::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /**
     * @return array<string, string|array<string|\Stringable|ValidationRule>>
     */
    public function rules(): array
    {
        return Mount::getRules();
    }
}
