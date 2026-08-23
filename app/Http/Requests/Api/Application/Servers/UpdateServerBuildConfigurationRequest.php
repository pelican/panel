<?php

namespace App\Http\Requests\Api\Application\Servers;

use App\Models\Server;
use Illuminate\Support\Collection;

class UpdateServerBuildConfigurationRequest extends ServerWriteRequest
{
    /**
     * Return the rules to validate this request against.
     */
    public function rules(): array
    {
        $rules = $this->route() ? Server::getRulesForUpdate($this->parameter('server', Server::class)) : Server::getRules();

        return [
            /** ID of the allocation the server listens on by default. */
            'allocation' => $rules['allocation_id'],
            /** Let the kernel's out of memory killer stop the server when it exceeds its memory limit. */
            'oom_killer' => $rules['oom_killer'],

            /** Resource limits applied to the server's container. */
            'limits' => 'sometimes|array',
            /** Memory the server may use, in MiB. Use `0` for unlimited. */
            'limits.memory' => $this->requiredToOptional($rules['memory']),
            /** Swap the server may use, in MiB. Use `0` to disable swap and `-1` for unlimited. */
            'limits.swap' => $this->requiredToOptional($rules['swap']),
            /** Block IO weight of the container relative to other containers on the node. */
            'limits.io' => $this->requiredToOptional($rules['io']),
            /** CPU the server may use, where each 100 is one core. Use `0` for unlimited. */
            'limits.cpu' => $this->requiredToOptional($rules['cpu']),
            /** Physical CPU threads the container is pinned to, such as `0`, `0-2` or `0,2`. */
            'limits.threads' => $this->requiredToOptional($rules['threads']),
            /** Disk space the server may use, in MiB. Use `0` for unlimited. */
            'limits.disk' => $this->requiredToOptional($rules['disk']),

            /** IDs of allocations to assign to the server on top of the ones it already has. */
            'add_allocations' => 'bail|array',
            /** ID of an allocation to assign to the server. */
            'add_allocations.*' => 'integer',
            /** IDs of allocations to take away from the server. */
            'remove_allocations' => 'bail|array',
            /** ID of an allocation to take away from the server. */
            'remove_allocations.*' => 'integer',

            /** Caps on the resources the server's own users may create for it. */
            'feature_limits' => 'required|array',
            /** How many databases may be created for the server. */
            'feature_limits.databases' => $rules['database_limit'],
            /** How many additional allocations may be assigned to the server. */
            'feature_limits.allocations' => $rules['allocation_limit'],
            /** How many backups may be stored for the server. */
            'feature_limits.backups' => $rules['backup_limit'],
        ];
    }

    /**
     * Convert the allocation field into the expected format for the service handler.
     *
     * @return array<array-key, string>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        $data['allocation_id'] = $data['allocation'];
        $data['database_limit'] = $data['feature_limits']['databases'] ?? null;
        $data['allocation_limit'] = $data['feature_limits']['allocations'] ?? null;
        $data['backup_limit'] = $data['feature_limits']['backups'] ?? null;
        unset($data['allocation'], $data['feature_limits']);

        // Adjust the limits field to match what is expected by the model.
        if (!empty($data['limits'])) {
            foreach ($data['limits'] as $key => $value) {
                $data[$key] = $value;
            }

            unset($data['limits']);
        }

        return $data;
    }

    /**
     * Custom attributes to use in error message responses.
     *
     * @return array<array-key, string>
     */
    public function attributes(): array
    {
        return [
            'add_allocations' => 'allocations to add',
            'remove_allocations' => 'allocations to remove',
            'add_allocations.*' => 'allocation to add',
            'remove_allocations.*' => 'allocation to remove',
            'feature_limits.databases' => 'Database Limit',
            'feature_limits.allocations' => 'Allocation Limit',
            'feature_limits.backups' => 'Backup Limit',
        ];
    }

    /**
     * Model rules mark these fields required, but on this endpoint they are only
     * required when the limits block is present at all.
     *
     * @param  array<array-key, mixed>  $rules
     * @return array<array-key, string>
     */
    protected function requiredToOptional(array $rules): array
    {
        if (!in_array('required', $rules)) {
            return $rules;
        }

        return (new Collection($rules))
            ->filter(function ($value) {
                return $value !== 'required';
            })
            ->prepend('required_with:limits')
            ->toArray();
    }
}
