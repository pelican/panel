<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;

final class NodeData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['allocations', 'servers'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function getResourceName(): string
    {
        return Node::RESOURCE_NAME;
    }

    public static function fromModel(Node $model): static
    {
        // Lists share one eager loaded node between many items, so relations an earlier item
        // loaded for its includes and the preloaded sums below are kept out of the attributes.
        $attributes = collect($model->toArray())
            ->except([...self::$availableIncludes, 'allocated_memory', 'allocated_disk', 'allocated_cpu'])
            ->mapWithKeys(fn ($value, $key) => [snake_case($key) => $value])
            ->toArray();

        $attributes[$model->getUpdatedAtColumn()] = self::formatTimestamp($model->updated_at);
        $attributes[$model->getCreatedAtColumn()] = self::formatTimestamp($model->created_at);

        // Node lists preload these sums, as allocated_* via Node::withAllocatedResources() or as
        // servers_sum_* in FindViableNodesService. A single node queries them instead.
        $loaded = $model->getAttributes();
        $prefix = array_key_exists('allocated_memory', $loaded) ? 'allocated_' : (array_key_exists('servers_sum_memory', $loaded) ? 'servers_sum_' : null);

        if ($prefix) {
            $attributes['allocated_resources'] = [
                'memory' => (int) $model->getAttribute($prefix . 'memory'),
                'disk' => (int) $model->getAttribute($prefix . 'disk'),
                'cpu' => (int) $model->getAttribute($prefix . 'cpu'),
            ];
        } else {
            $resources = $model->servers()->select(['memory', 'disk', 'cpu'])->get();

            $attributes['allocated_resources'] = [
                'memory' => $resources->sum('memory'),
                'disk' => $resources->sum('disk'),
                'cpu' => $resources->sum('cpu'),
            ];
        }

        return new self($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public static function includes(): array
    {
        return [
            'allocations' => function (Node $node, IncludeContext $context): array {
                if (!$context->allowsAdmin(Allocation::RESOURCE_NAME)) {
                    return $context->null();
                }

                $node->loadMissing('allocations');

                return $context->collection($node->getRelation('allocations'), AllocationData::class);
            },
            'servers' => function (Node $node, IncludeContext $context): array {
                if (!$context->allowsAdmin(Server::RESOURCE_NAME)) {
                    return $context->null();
                }

                $node->loadMissing('servers');

                return $context->collection($node->getRelation('servers'), ServerData::class);
            },
        ];
    }
}
