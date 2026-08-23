<?php

namespace App\Http\Requests\Api\Application\Servers;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\Objects\DeploymentObject;
use App\Models\Server;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServerRequest extends ApplicationApiRequest
{
    protected ?string $resource = Server::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /**
     * Rules to be applied to this request.
     */
    public function rules(): array
    {
        $rules = Server::getRules();

        return [
            /** Identifier from your own system, stored alongside the server so you can match the two up later. */
            'external_id' => $rules['external_id'],
            /** Name the server is shown under in the Panel. */
            'name' => $rules['name'],
            /** Free form text describing the server. */
            'description' => array_merge(['nullable'], $rules['description']),
            /** ID of the user who owns the server. */
            'user' => $rules['owner_id'],
            /** ID of the egg the server runs, which decides its default startup command, variables and images. */
            'egg' => $rules['egg_id'],
            /** Docker image the server container is built from. Defaults to the egg's first image when omitted. */
            'docker_image' => 'sometimes|string',
            /** Command run inside the container to start the server. Defaults to the egg's startup command when omitted. */
            'startup' => 'sometimes|string',
            /** Values for the egg's environment variables, keyed by variable name. */
            'environment' => 'present|array',
            /** Skip the egg's install script when deploying the server. */
            'skip_scripts' => 'sometimes|boolean',
            /** Let the kernel's out of memory killer stop the server when it exceeds its memory limit. */
            'oom_killer' => 'sometimes|boolean',

            /** Resource limits applied to the server's container. */
            'limits' => 'required|array',
            /** Memory the server may use, in MiB. Use `0` for unlimited. */
            'limits.memory' => $rules['memory'],
            /** Swap the server may use, in MiB. Use `0` to disable swap and `-1` for unlimited. */
            'limits.swap' => $rules['swap'],
            /** Disk space the server may use, in MiB. Use `0` for unlimited. */
            'limits.disk' => $rules['disk'],
            /** Block IO weight of the container relative to other containers on the node. */
            'limits.io' => $rules['io'],
            /** Physical CPU threads the container is pinned to, such as `0`, `0-2` or `0,2`. Leave empty to let the node decide. */
            'limits.threads' => $rules['threads'],
            /** CPU the server may use, where each 100 is one core. Use `0` for unlimited. */
            'limits.cpu' => $rules['cpu'],

            /** Caps on the resources the server's own users may create for it. */
            'feature_limits' => 'required|array',
            /** How many databases may be created for the server. */
            'feature_limits.databases' => $rules['database_limit'],
            /** How many additional allocations may be assigned to the server. */
            'feature_limits.allocations' => $rules['allocation_limit'],
            /** How many backups may be stored for the server. */
            'feature_limits.backups' => $rules['backup_limit'],

            // Placeholders for rules added in withValidator() function.
            /** ID of the allocation the server listens on by default. Required unless `deploy` is given. */
            'allocation.default' => '',
            /** ID of a further allocation to assign to the server. */
            'allocation.additional.*' => '',

            /** Ask the Panel to pick a node and allocation instead of naming one in `allocation`. */
            'deploy' => 'sometimes|required|array',
            /** Only deploy to nodes carrying all of these tags. */
            'deploy.tags' => 'array',
            /** A tag the destination node has to carry. */
            'deploy.tags.*' => 'required_with:deploy.tags|string|min:1',
            /** Only pick an allocation whose IP has no other server on it. */
            'deploy.dedicated_ip' => 'required_with:deploy|boolean',
            /** Restrict the allocation search to these ports. */
            'deploy.port_range' => 'array',
            /** A single port or an inclusive range such as `25565-25570`. */
            'deploy.port_range.*' => 'string',
            /** Start the server once its installation finishes. */
            'start_on_completion' => 'sometimes|boolean',
        ];
    }

    /**
     * Normalize the data into a format that can be consumed by the service.
     *
     * @return array{
     *     external_id: int,
     *     name: string,
     *     description: string,
     *     owner_id: int,
     *     egg_id: int,
     *     image: string,
     *     startup: string,
     *     environment: string,
     *     memory: int,
     *     swap: int,
     *     disk: int,
     *     io: int,
     *     cpu: int,
     *     threads: int,
     *     skip_scripts: bool,
     *     allocation_id: int,
     *     allocation_additional: int[],
     *     start_on_completion: bool,
     *     database_limit: int,
     *     allocation_limit: int,
     *     backup_limit: int,
     *     oom_killer: bool,
     * }
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return [
            'external_id' => array_get($data, 'external_id'),
            'name' => array_get($data, 'name'),
            'description' => array_get($data, 'description'),
            'owner_id' => array_get($data, 'user'),
            'egg_id' => array_get($data, 'egg'),
            'image' => array_get($data, 'docker_image'),
            'startup' => array_get($data, 'startup'),
            'environment' => array_get($data, 'environment'),
            'memory' => array_get($data, 'limits.memory'),
            'swap' => array_get($data, 'limits.swap'),
            'disk' => array_get($data, 'limits.disk'),
            'io' => array_get($data, 'limits.io'),
            'cpu' => array_get($data, 'limits.cpu'),
            'threads' => array_get($data, 'limits.threads'),
            'skip_scripts' => array_get($data, 'skip_scripts', false),
            'allocation_id' => array_get($data, 'allocation.default'),
            'allocation_additional' => array_get($data, 'allocation.additional'),
            'start_on_completion' => array_get($data, 'start_on_completion', false),
            'database_limit' => array_get($data, 'feature_limits.databases'),
            'allocation_limit' => array_get($data, 'feature_limits.allocations'),
            'backup_limit' => array_get($data, 'feature_limits.backups'),
            'oom_killer' => array_get($data, 'oom_killer'),
        ];
    }

    /*
     * Run validation after the rules above have been applied.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('allocation.default', [
            'required', 'integer', 'bail',
            Rule::exists('allocations', 'id')->where(function ($query) {
                $query->whereNull('server_id');
            }),
        ], function ($input) {
            return !$input->deploy;
        });

        $validator->sometimes('allocation.additional.*', [
            'integer',
            Rule::exists('allocations', 'id')->where(function ($query) {
                $query->whereNull('server_id');
            }),
        ], function ($input) {
            return !$input->deploy;
        });

        $validator->sometimes('deploy.tags', 'present', function ($input) {
            return $input->deploy;
        });

        $validator->sometimes('deploy.port_range', 'present', function ($input) {
            return $input->deploy;
        });
    }

    /**
     * Return a deployment object that can be passed to the server creation service.
     */
    public function getDeploymentObject(): ?DeploymentObject
    {
        if (is_null($this->input('deploy'))) {
            return null;
        }

        $object = new DeploymentObject();
        $object->setDedicated($this->input('deploy.dedicated_ip', false));
        $object->setTags($this->input('deploy.tags', []));
        $object->setPorts($this->input('deploy.port_range', []));

        return $object;
    }
}
