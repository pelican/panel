<?php

namespace App\Http\Requests\Api\Application\Servers;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\Server;
use App\Services\Acl\Api\AdminAcl;

class UpdateServerStartupRequest extends ApplicationApiRequest
{
    protected ?string $resource = Server::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /**
     * Validation rules to run the input against.
     */
    public function rules(): array
    {
        $rules = $this->route() ? Server::getRulesForUpdate($this->parameter('server', Server::class)) : Server::getRules();

        return [
            /** Command run inside the container to start the server. */
            'startup' => 'sometimes|string',
            /** Values for the egg's environment variables, keyed by variable name. */
            'environment' => 'present|array',
            /** ID of the egg the server runs. Changing this reinstalls the server. */
            'egg' => $rules['egg_id'],
            /** Docker image the server container is built from. */
            'image' => 'sometimes|string',
            /** Skip the egg's install script when the server is reinstalled. */
            'skip_scripts' => 'present|boolean',
        ];
    }

    /**
     * Return the validated data in a format that is expected by the service.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return collect($data)->only(['startup', 'environment', 'skip_scripts'])->merge([
            'egg_id' => array_get($data, 'egg'),
            'docker_image' => array_get($data, 'image'),
        ])->toArray();
    }
}
