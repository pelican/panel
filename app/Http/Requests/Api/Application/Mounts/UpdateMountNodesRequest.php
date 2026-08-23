<?php

namespace App\Http\Requests\Api\Application\Mounts;

class UpdateMountNodesRequest extends StoreMountRequest
{
    /**
     * @param  array<string, string|string[]>|null  $rules
     * @return array<string, string|string[]>
     */
    public function rules(?array $rules = null): array
    {
        return [
            /** IDs of the nodes this mount is available on. Nodes left out of this list are unassigned. */
            'nodes' => 'required|array|exists:nodes,id',
            /** ID of a node this mount is available on. */
            'nodes.*' => 'integer',
        ];
    }
}
