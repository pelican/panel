<?php

namespace App\Http\Requests\Api\Application\Allocations;

use App\Enums\AllocationProtocol;
use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\Allocation;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Validation\Rule;

class UpdateAllocationRequest extends ApplicationApiRequest
{
    protected ?string $resource = Allocation::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'protocol' => ['required', Rule::enum(AllocationProtocol::class)],
        ];
    }
}
