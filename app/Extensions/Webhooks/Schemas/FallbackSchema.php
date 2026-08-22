<?php

namespace App\Extensions\Webhooks\Schemas;

use App\Models\WebhookConfiguration;

/**
 * Delivery for webhooks whose type has no registered schema, a plugin type after the
 * plugin was removed for example. Never registered with the type service, so it cannot
 * be selected, listed or detected; it only keeps stored rows delivering with their
 * saved payload template, headers and the configured timeout.
 */
class FallbackSchema extends RegularSchema
{
    public function getId(): string
    {
        return 'fallback';
    }

    /**
     * @param  array<string, mixed>  $eventData
     * @return array<string, mixed>
     */
    public function preparePayload(WebhookConfiguration $webhookConfiguration, array $eventData): array
    {
        if (blank($webhookConfiguration->payload)) {
            return $eventData;
        }

        $payload = json_encode($webhookConfiguration->payload);
        if ($payload === false) {
            return $eventData;
        }

        return json_decode($webhookConfiguration->replaceVars($eventData, $payload), true) ?? $eventData;
    }
}
