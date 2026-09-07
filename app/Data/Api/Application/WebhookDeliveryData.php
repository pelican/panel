<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Models\Webhook;

final class WebhookDeliveryData extends ApiResource
{
    public function __construct(
        public int $id,
        public int $webhook_configuration_id,
        public string $event,
        public string $endpoint,
        public mixed $payload,
        public bool $successful,
        public ?string $successful_at,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return 'webhook_delivery';
    }

    public static function fromModel(Webhook $model): static
    {
        return new self(
            id: $model->id,
            webhook_configuration_id: $model->webhook_configuration_id,
            event: $model->event,
            endpoint: $model->endpoint,
            payload: $model->payload,
            successful: $model->successful_at !== null,
            successful_at: $model->successful_at?->toAtomString(),
            created_at: $model->created_at?->toAtomString(),
            updated_at: $model->updated_at?->toAtomString(),
        );
    }
}
