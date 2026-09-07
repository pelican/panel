<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Facades\WebhookTypes;
use App\Models\Server;
use App\Models\WebhookConfiguration;

final class WebhookConfigurationData extends ApiResource
{
    /**
     * Most recent deliveries returned by the `deliveries` include.
     */
    public const DeliveryLimit = 25;

    /** @var string[] */
    public static array $availableIncludes = [
        'server',
        'deliveries',
    ];

    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $description,
        public string $scope,
        public ?int $server_id,
        public ?string $type,
        public bool $type_available,
        public string $endpoint,
        public mixed $events,
        public mixed $payload,
        public mixed $headers,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return WebhookConfiguration::RESOURCE_NAME;
    }

    public static function fromModel(WebhookConfiguration $model): static
    {
        return new self(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            scope: $model->scope->value,
            server_id: $model->server_id,
            type: $model->type,
            // False when the type comes from a plugin that is not currently loaded
            type_available: WebhookTypes::has($model->type),
            endpoint: $model->endpoint,
            events: $model->events,
            payload: $model->payload,
            headers: $model->headers,
            created_at: $model->created_at?->toAtomString(),
            updated_at: $model->updated_at?->toAtomString(),
        );
    }

    public static function includes(): array
    {
        return [
            'server' => function (WebhookConfiguration $webhook, IncludeContext $context): array {
                if (!$webhook->server_id || !$context->allowsAdmin(Server::RESOURCE_NAME)) {
                    return $context->null();
                }

                $webhook->loadMissing('server');

                return $context->item($webhook->getRelation('server'), ServerData::class);
            },
            // A busy webhook accumulates a delivery per fired event until pruning removes
            // them, so this include is deliberately bounded and ordered rather than
            // loading the whole relation into memory.
            'deliveries' => function (WebhookConfiguration $webhook, IncludeContext $context): array {
                $deliveries = $webhook->webhooks()
                    ->orderByDesc('id')
                    ->limit(self::DeliveryLimit)
                    ->get();

                return $context->collection($deliveries, WebhookDeliveryData::class);
            },
        ];
    }
}
