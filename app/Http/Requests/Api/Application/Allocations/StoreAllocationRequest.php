<?php

namespace App\Http\Requests\Api\Application\Allocations;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\Allocation;
use App\Services\Acl\Api\AdminAcl;

class StoreAllocationRequest extends ApplicationApiRequest
{
    protected ?string $resource = Allocation::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /** @return array<string, string|string[]> */
    public function rules(): array
    {
        return [
            /** IP address on the node the allocations are created for. */
            'ip' => 'required|string',
            /** Friendly hostname shown in place of the IP address in the Panel. */
            'alias' => 'sometimes|nullable|string|max:255',
            /** Ports to create allocations for on the given IP address. */
            'ports' => 'required|array',
            /** A single port or an inclusive range such as `25565-25570`. */
            'ports.*' => 'string',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return [
            'allocation_ip' => $data['ip'],
            'allocation_ports' => $data['ports'],
            'allocation_alias' => $data['alias'] ?? null,
        ];
    }
}
