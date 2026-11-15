<?php

namespace App\Services\Backups;

use App\Models\Allocation;
use App\Models\EggVariable;
use App\Models\Mount;
use App\Models\Server;
use Carbon\Carbon;

/**
 * Builds the .pelicanconfig stored with a backup. It holds what is needed to
 * create the server again from the backup. Only variables users can see are
 * included, and nothing about the owner or node, which are chosen when the
 * server is restored.
 */
class ServerConfigExporterService
{
    public const VERSION = 'PLCN_SRV_v1';

    /**
     * @return array<string, mixed>
     */
    public function handle(Server $server): array
    {
        $server->loadMissing(['egg', 'allocation', 'allocations', 'mounts']);

        $additional = $server->allocations
            ->reject(fn (Allocation $allocation) => $allocation->id === $server->allocation_id)
            ->sortBy('id')
            ->map(fn (Allocation $allocation) => $this->allocation($allocation))
            ->values()
            ->all();

        // The variables relation joins on this server's id, so it cannot be eager loaded.
        $variables = $server->variables()->get()
            ->filter(fn (EggVariable $variable) => $variable->user_viewable)
            ->mapWithKeys(fn (EggVariable $variable) => [
                $variable->env_variable => $variable->server_value ?? $variable->default_value,
            ])
            ->all();

        return [
            '_comment' => 'DO NOT EDIT: FILE GENERATED AUTOMATICALLY BY PANEL',
            'meta' => [
                'version' => self::VERSION,
            ],
            'exported_at' => Carbon::now()->toAtomString(),
            'server' => [
                'uuid' => $server->uuid,
                'name' => $server->name,
                'description' => $server->description,
                'external_id' => $server->external_id,
            ],
            'egg' => [
                'uuid' => $server->egg->uuid,
                'name' => $server->egg->name,
                'update_url' => $server->egg->update_url,
            ],
            'image' => $server->image,
            'startup' => $server->startup,
            'limits' => [
                'memory' => $server->memory,
                'swap' => $server->swap,
                'disk' => $server->disk,
                'io' => $server->io,
                'cpu' => $server->cpu,
                'threads' => $server->threads,
                'oom_killer' => $server->oom_killer,
            ],
            'feature_limits' => [
                'databases' => $server->database_limit,
                'allocations' => $server->allocation_limit,
                'backups' => $server->backup_limit,
            ],
            'allocations' => [
                'default' => $server->allocation ? $this->allocation($server->allocation) : null,
                'additional' => $additional,
            ],
            'variables' => (object) $variables,
            'docker_labels' => (object) ($server->docker_labels ?? []),
            'mounts' => $server->mounts->map(fn (Mount $mount) => $mount->uuid)->values()->all(),
        ];
    }

    /**
     * @return array{ip: string, ip_alias: string|null, port: int, notes: string|null}
     */
    private function allocation(Allocation $allocation): array
    {
        return [
            'ip' => $allocation->ip,
            'ip_alias' => $allocation->ip_alias,
            'port' => $allocation->port,
            'notes' => $allocation->notes,
        ];
    }
}
