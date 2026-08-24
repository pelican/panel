<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;

final class AllocationData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['node', 'server'];

    public function __construct(
        public int $id,
        public string $ip,
        public ?string $alias,
        public int $port,
        public ?string $notes,
        public bool $assigned,
    ) {}

    public static function getResourceName(): string
    {
        return Allocation::RESOURCE_NAME;
    }

    public static function fromModel(Allocation $model): static
    {
        return new self(
            id: $model->id,
            ip: $model->ip,
            alias: $model->ip_alias,
            port: $model->port,
            notes: $model->notes,
            assigned: !is_null($model->server_id),
        );
    }

    public static function includes(): array
    {
        return [
            'node' => function (Allocation $allocation, IncludeContext $context): array {
                if (!$context->allowsAdmin(Node::RESOURCE_NAME)) {
                    return $context->null();
                }

                return $context->item($allocation->node, NodeData::class);
            },
            'server' => function (Allocation $allocation, IncludeContext $context): array {
                if (!$context->allowsAdmin(Server::RESOURCE_NAME) || !$allocation->server) {
                    return $context->null();
                }

                return $context->item($allocation->server, ServerData::class);
            },
        ];
    }
}
