<?php

namespace App\Http\Requests\Api\Application\Mounts;

use App\Models\Mount;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;

// The rules come straight off the model, so there is no rules array to hang comments on.
#[BodyParameter('name', description: 'Name the mount is shown under in the Panel.')]
#[BodyParameter('description', description: 'Free form text describing the mount.')]
#[BodyParameter('source', description: 'Absolute path on the node that is mounted into the server.')]
#[BodyParameter('target', description: 'Absolute path inside the server container the source appears at.')]
#[BodyParameter('read_only', description: 'Mount the source read only so servers cannot write to it.')]
#[BodyParameter('user_mountable', description: 'Whether server owners may attach this mount themselves.')]
class UpdateMountRequest extends StoreMountRequest
{
    /**
     * @param  array<string, string|array<string|\Stringable|ValidationRule>>|null  $rules
     * @return array<string, string|array<string|\Stringable|ValidationRule>>
     */
    public function rules(?array $rules = null): array
    {
        // No route to read the mount off of when the rules are being inspected rather than applied,
        // which is how the API documentation is generated.
        if (!$this->route()) {
            return $rules ?? Mount::getRules();
        }

        /** @var Mount $mount */
        $mount = $this->route()->parameter('mount');

        return Mount::getRulesForUpdate($mount);
    }
}
