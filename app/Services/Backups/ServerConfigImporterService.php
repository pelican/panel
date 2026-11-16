<?php

namespace App\Services\Backups;

use App\Exceptions\DisplayException;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\Mount;
use App\Models\Node;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * Reads a .pelicanconfig and turns it into the state of the create server
 * wizard, so a server can be created again from a backup.
 */
class ServerConfigImporterService
{
    /**
     * Validates a .pelicanconfig and returns it in the current format.
     *
     * @param  array<string, mixed>|string  $config
     * @return array<string, mixed>
     *
     * @throws DisplayException
     */
    public function parse(array|string $config): array
    {
        if (is_string($config)) {
            $config = json_decode($config, true);
            if (!is_array($config)) {
                throw new DisplayException(trans('admin/backup.restore.invalid_config'));
            }
        }

        $config = match (Arr::get($config, 'meta.version')) {
            ServerConfigExporterService::VERSION => $config,
            default => throw new DisplayException(trans('admin/backup.restore.unsupported_config', ['version' => (string) Arr::get($config, 'meta.version')])),
        };

        $validator = Validator::make($config, [
            'server' => ['nullable', 'array'],
            'server.name' => ['nullable', 'string', 'max:255'],
            'server.description' => ['nullable', 'string'],
            'egg' => ['required', 'array'],
            'egg.uuid' => ['nullable', 'string'],
            'egg.name' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'startup' => ['nullable', 'string'],
            'limits' => ['nullable', 'array'],
            'limits.*' => ['nullable'],
            'feature_limits' => ['nullable', 'array'],
            'feature_limits.*' => ['nullable', 'integer', 'min:0'],
            'allocations' => ['nullable', 'array'],
            'allocations.default' => ['nullable', 'array'],
            'allocations.default.ip' => ['required_with:allocations.default', 'string'],
            'allocations.default.port' => ['required_with:allocations.default', 'integer'],
            'allocations.additional' => ['nullable', 'array'],
            'allocations.additional.*.ip' => ['required', 'string'],
            'allocations.additional.*.port' => ['required', 'integer'],
            'variables' => ['nullable', 'array'],
            'docker_labels' => ['nullable', 'array'],
            'mounts' => ['nullable', 'array'],
            'mounts.*' => ['string'],
        ]);

        if ($validator->fails()) {
            throw new DisplayException(trans('admin/backup.restore.invalid_config') . ' ' . $validator->errors()->first());
        }

        // Lists the validator accepts as null are used as arrays below.
        foreach (['variables', 'allocations.additional', 'docker_labels', 'mounts', 'limits', 'feature_limits'] as $key) {
            if (Arr::has($config, $key) && Arr::get($config, $key) === null) {
                Arr::set($config, $key, []);
            }
        }

        return $config;
    }

    /**
     * The egg the configuration was made with, if it exists on this Panel.
     *
     * @param  array<string, mixed>  $config
     */
    public function findEgg(array $config): ?Egg
    {
        $uuid = Arr::get($config, 'egg.uuid');

        return $uuid ? Egg::query()->where('uuid', $uuid)->first() : null;
    }

    /**
     * The state for the create server wizard. The owner, node and allocations
     * are not included; allocations are matched with matchAllocations().
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function toFormState(array $config): array
    {
        $state = [
            'name' => Arr::get($config, 'server.name'),
            'description' => Arr::get($config, 'server.description'),
            // The files come from the backup, so the install script must not run.
            'skip_scripts' => 1,
        ];

        $limits = Arr::get($config, 'limits', []);
        // Toggle buttons are keyed 0 and 1, and a false state matches neither, so they are set as ints.
        foreach (['cpu' => 'unlimited_cpu', 'memory' => 'unlimited_mem', 'disk' => 'unlimited_disk'] as $limit => $toggle) {
            if (isset($limits[$limit])) {
                $state[$limit] = (int) $limits[$limit];
                $state[$toggle] = (int) ((int) $limits[$limit] === 0);
            }
        }
        if (isset($limits['io'])) {
            $state['io'] = (int) $limits['io'];
        }
        if (isset($limits['swap'])) {
            $swap = (int) $limits['swap'];
            $state['swap'] = $swap;
            $state['swap_support'] = match (true) {
                $swap < 0 => 'unlimited',
                $swap === 0 => 'disabled',
                default => 'limited',
            };
        }
        if (isset($limits['threads']) && $limits['threads'] !== '') {
            $state['cpu_pinning'] = 1;
            $state['threads'] = array_values(array_filter(explode(',', (string) $limits['threads']), fn ($thread) => $thread !== ''));
        }
        if (isset($limits['oom_killer'])) {
            $state['oom_killer'] = (int) (bool) $limits['oom_killer'];
        }

        foreach (['allocations' => 'allocation_limit', 'databases' => 'database_limit', 'backups' => 'backup_limit'] as $limit => $field) {
            $value = Arr::get($config, "feature_limits.$limit");
            if ($value !== null) {
                $state[$field] = (int) $value;
            }
        }

        $state['docker_labels'] = Arr::get($config, 'docker_labels') ?: [];

        $mounts = Arr::get($config, 'mounts', []);
        if ($mounts) {
            $state['mounts'] = Mount::query()->whereIn('uuid', $mounts)->pluck('id')->all();
        }

        $egg = $this->findEgg($config);
        if (!$egg) {
            return $state;
        }

        $state['egg_id'] = $egg->id;

        $startup = Arr::get($config, 'startup');
        if ($startup) {
            $state['startup'] = $startup;
            $state['select_startup'] = in_array($startup, $egg->startup_commands ?? []) ? $startup : 'custom';
        }

        $image = Arr::get($config, 'image');
        if ($image) {
            $state['image'] = $image;
            $state['select_image'] = in_array($image, $egg->docker_images ?? []) ? $image : 'ghcr.io/custom-image';
        }

        // Variables that were not saved in the configuration use the egg default.
        $values = Arr::get($config, 'variables', []);
        $environment = [];
        $serverVariables = [];
        foreach ($egg->variables()->orderBy('sort')->get() as $variable) {
            /** @var EggVariable $variable */
            $value = array_key_exists($variable->env_variable, $values) ? $values[$variable->env_variable] : $variable->default_value;
            $environment[$variable->env_variable] = $value;
            $serverVariables[] = array_merge($variable->toArray(), [
                'variable_id' => $variable->id,
                'variable_value' => $value,
            ]);
        }
        $state['environment'] = $environment;
        $state['server_variables'] = $serverVariables;

        return $state;
    }

    /**
     * The egg's variables the configuration has no value for, so they use the egg
     * default. Backups leave out variables users cannot see, and variables added to
     * the egg after the backup was made are not in it either. A required variable
     * with an empty default has to be filled in before the server can be created.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{name: string, env_variable: string, required_empty: bool}>
     */
    public function defaultedVariables(array $config): array
    {
        $egg = $this->findEgg($config);
        if (!$egg) {
            return [];
        }

        $values = Arr::get($config, 'variables', []);

        return EggVariable::query()->where('egg_id', $egg->id)->orderBy('sort')->get()
            ->reject(fn (EggVariable $variable) => array_key_exists($variable->env_variable, $values))
            ->map(fn (EggVariable $variable) => [
                'name' => $variable->name,
                'env_variable' => $variable->env_variable,
                'required_empty' => $variable->required && ($variable->default_value ?? '') === '',
            ])
            ->values()
            ->all();
    }

    /**
     * Finds the configuration's allocations on the given node. Allocations that
     * are free are selected, the rest are returned as missing.
     *
     * @param  array<string, mixed>  $config
     * @return array{allocation_id: int|null, allocation_additional: int[], missing: string[]}
     */
    public function matchAllocations(array $config, ?Node $node): array
    {
        $wanted = array_values(array_filter([
            Arr::get($config, 'allocations.default'),
            ...Arr::get($config, 'allocations.additional', []),
        ]));

        $result = ['allocation_id' => null, 'allocation_additional' => [], 'missing' => []];

        foreach ($wanted as $index => $allocation) {
            $match = $node ? Allocation::query()
                ->where('node_id', $node->id)
                ->where('ip', $allocation['ip'])
                ->where('port', $allocation['port'])
                ->whereNull('server_id')
                ->value('id') : null;

            if (!$match) {
                $result['missing'][] = "{$allocation['ip']}:{$allocation['port']}";

                continue;
            }

            if ($index === 0 || $result['allocation_id'] === null) {
                $result['allocation_id'] = $match;
            } else {
                $result['allocation_additional'][] = $match;
            }
        }

        return $result;
    }

    /**
     * The original addresses that can be created on the given node, because
     * the node has their IP, grouped by IP.
     *
     * @param  string[]  $missing
     * @return array<string, int[]>
     */
    public function creatableAllocations(array $missing, Node $node): array
    {
        $ips = $node->ipAddresses();
        $taken = Allocation::query()->where('node_id', $node->id)->get(['ip', 'port'])->map(fn (Allocation $allocation) => "$allocation->ip:$allocation->port")->all();

        $creatable = [];
        foreach ($missing as $address) {
            [$ip, $port] = [substr($address, 0, strrpos($address, ':')), (int) substr($address, strrpos($address, ':') + 1)];
            if (in_array($ip, $ips) && !in_array($address, $taken)) {
                $creatable[$ip][] = $port;
            }
        }

        return $creatable;
    }
}
