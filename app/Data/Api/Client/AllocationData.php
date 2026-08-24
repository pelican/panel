<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Models\Allocation;

final class AllocationData extends ApiResource
{
    public function __construct(
        public int $id,
        public string $ip,
        public ?string $ip_alias,
        public int $port,
        public ?string $notes,
        public bool $is_default,
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
            ip_alias: $model->ip_alias,
            port: $model->port,
            notes: $model->notes,
            is_default: $model->server->allocation_id === $model->id,
        );
    }
}
