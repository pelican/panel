<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use Illuminate\Support\Arr;

final class StatsData extends ApiResource
{
    /**
     * @param  array<string, int|float>  $resources
     */
    public function __construct(
        public string $current_state,
        public bool $is_suspended,
        public array $resources,
    ) {}

    public static function getResourceName(): string
    {
        return 'stats';
    }

    /**
     * @param  array<string, mixed>  $model
     */
    public static function fromModel(array $model): static
    {
        return new self(
            current_state: Arr::get($model, 'state', 'stopped'),
            is_suspended: Arr::get($model, 'is_suspended', false),
            resources: [
                'memory_bytes' => Arr::get($model, 'utilization.memory_bytes', 0),
                'cpu_absolute' => Arr::get($model, 'utilization.cpu_absolute', 0),
                'disk_bytes' => Arr::get($model, 'utilization.disk_bytes', 0),
                'network_rx_bytes' => Arr::get($model, 'utilization.network.rx_bytes', 0),
                'network_tx_bytes' => Arr::get($model, 'utilization.network.tx_bytes', 0),
                'uptime' => Arr::get($model, 'utilization.uptime', 0),
            ],
        );
    }
}
