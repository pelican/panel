<?php

namespace App\Services\Servers;

use App\Extensions\Features\FeatureService;
use App\Models\Mount;
use App\Models\Server;

class ServerConfigurationStructureService
{
    public function __construct(private EnvironmentService $environment, private FeatureService $featureService) {}

    /**
     * Return a configuration array for a specific server when passed a server model.
     *
     * DO NOT MODIFY THIS FUNCTION. This powers legacy code handling for the new daemon
     * daemon, if you modify the structure eggs will break unexpectedly.
     *
     * @param  array<array-key, mixed>  $override
     * @return array<array-key, mixed>
     */
    public function handle(Server $server, array $override = []): array
    {
        $clone = $server;
        // If any overrides have been set on this call make sure to update them on the
        // cloned instance so that the configuration generated uses them.
        if (!empty($override)) {
            $clone = $server->fresh();
            foreach ($override as $key => $value) {
                $clone->setAttribute($key, $value);
            }
        }

        return $this->returnFormat($clone);
    }

    /**
     * Returns the data format used for the daemon.
     *
     * @return array{
     *     id: int,
     *     uuid: string,
     *     meta: array{name: string, description: string|null},
     *     suspended: bool,
     *     environment: array<string, mixed>,
     *     invocation: string,
     *     skip_egg_scripts: bool,
     *     build: array{
     *         memory_limit: int,
     *         swap: int,
     *         io_weight: int,
     *         cpu_limit: int,
     *         threads: ?string,
     *         disk_space: int,
     *         oom_killer: bool,
     *     },
     *     container: array{image: string, requires_rebuild: false},
     *     allocations: array{
     *         force_outgoing_ip: bool,
     *         default: array{ip: string, port: int},
     *         mappings: array<string, array<int>>,
     *     },
     *     egg: array{id: string|null, file_denylist: string[]|null, features: string[][]},
     *     labels?: array<mixed>,
     *     mounts?: array<mixed>,
     * }
     *
     * @todo convert to API Resource
     */
    protected function returnFormat(Server $server): array
    {
        $response = [
            'id' => $server->id,
            'uuid' => $server->uuid,
            'meta' => [
                'name' => $server->name,
                'description' => $server->description,
            ],
            'suspended' => $server->isSuspended(),
            'environment' => $this->environment->handle($server),
            'invocation' => $server->startup,
            'skip_egg_scripts' => $server->skip_scripts,
            'build' => [
                'memory_limit' => (int) round(config('panel.use_binary_prefix') ? $server->memory : $server->memory / 1.048576),
                'swap' => (int) round(config('panel.use_binary_prefix') ? $server->swap : $server->swap / 1.048576),
                'io_weight' => $server->io,
                'cpu_limit' => $server->cpu,
                'threads' => $server->threads,
                'disk_space' => (int) round(config('panel.use_binary_prefix') ? $server->disk : $server->disk / 1.048576),
                'oom_killer' => $server->oom_killer,
            ],
            'container' => [
                'image' => $server->image,
                'requires_rebuild' => false,
            ],
            'allocations' => [
                'force_outgoing_ip' => $server->egg->force_outgoing_ip,
                'default' => [
                    'ip' => $server->allocation->ip ?? '127.0.0.1',
                    'port' => $server->allocation->port ?? 0,
                ],
                'mappings' => $server->getAllocationMappings(),
            ],
            'egg' => [
                'id' => $server->egg->uuid,
                'file_denylist' => $server->egg->inherit_file_denylist,
                'features' => $this->featureService->getMappings($server->egg->features),
            ],
        ];

        if (!empty($server->docker_labels)) {
            $response['labels'] = $server->docker_labels;
        }

        if ($server->mounts->isNotEmpty()) {
            $response['mounts'] = $server->mounts->map(function (Mount $mount) {
                return [
                    'source' => $mount->source,
                    'target' => $mount->target,
                    'read_only' => $mount->read_only,
                ];
            })->toArray();
        }

        return $response;
    }
}
