<?php

namespace App\Http\Requests\Api\Application\Mounts;

class UpdateMountEggsRequest extends StoreMountRequest
{
    /**
     * @param  array<string, string|string[]>|null  $rules
     * @return array<string, string|string[]>
     */
    public function rules(?array $rules = null): array
    {
        return [
            /** IDs of eggs to add to this mount. Eggs already assigned are left untouched. */
            'eggs' => 'required|array|exists:eggs,id',
            /** ID of an egg allowed to use this mount. */
            'eggs.*' => 'integer',
        ];
    }
}
