<?php

namespace App\Http\Requests\Api\Application\Mounts;

class UpdateMountServersRequest extends StoreMountRequest
{
    /**
     * @param  array<string, string|string[]>|null  $rules
     * @return array<string, string|string[]>
     */
    public function rules(?array $rules = null): array
    {
        return [
            /** IDs of the servers this mount is attached to. Servers left out of this list are detached. */
            'servers' => 'required|array|exists:servers,id',
            /** ID of a server this mount is attached to. */
            'servers.*' => 'integer',
        ];
    }
}
