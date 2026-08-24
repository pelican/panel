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

        $payload = $webhookConfiguration->payload;
        array_walk_recursive($payload, function (&$value) use ($webhookConfiguration, $eventData) {
            if (is_string($value)) {
                $value = $webhookConfiguration->replaceVars($eventData, $value);
            }
        });

        return $payload;
    }
}
