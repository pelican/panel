<?php

namespace App\Http\Requests\Api\Application\Nodes;

class GetDeployableNodesRequest extends GetNodesRequest
{
    public function rules(): array
    {
        return [
            /** Page of results to return. */
            'page' => 'integer',
            /** Memory the server will need, in MiB. Nodes without this much left over are left out. */
            'memory' => 'required|integer|min:0',
            /** Disk space the server will need, in MiB. Nodes without this much left over are left out. */
            'disk' => 'required|integer|min:0',
            /** CPU the server will need, where each 100 is one core. */
            'cpu' => 'sometimes|integer|min:0',
            /** Only return nodes carrying all of these tags. */
            'tags' => 'sometimes|array',
        ];
    }
}
