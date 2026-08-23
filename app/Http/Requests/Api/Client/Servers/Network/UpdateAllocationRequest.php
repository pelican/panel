<?php

namespace App\Http\Requests\Api\Client\Servers\Network;

use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\Allocation;

class UpdateAllocationRequest extends ClientApiRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::AllocationUpdate;
    }

    public function rules(): array
    {
        $rules = Allocation::getRules();

        return [
            /** Note describing what the allocation is used for. Send `null` to clear it. */
            'notes' => array_merge($rules['notes'], ['present']),
        ];
    }
}
