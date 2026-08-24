<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Models\ApiKey;

final class ApiKeyData extends ApiResource
{
    /**
     * @param  string[]  $allowed_ips
     */
    public function __construct(
        public string $identifier,
        public ?string $description,
        public array $allowed_ips,
        public ?string $last_used_at,
        public string $created_at,
    ) {}

    public static function getResourceName(): string
    {
        return ApiKey::RESOURCE_NAME;
    }

    public static function fromModel(ApiKey $model): static
    {
        return new self(
            identifier: $model->identifier,
            description: $model->memo,
            allowed_ips: $model->allowed_ips,
            last_used_at: $model->last_used_at ? $model->last_used_at->toAtomString() : null,
            created_at: $model->created_at->toAtomString(),
        );
    }
}
