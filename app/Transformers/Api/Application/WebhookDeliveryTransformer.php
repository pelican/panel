<?php

namespace App\Transformers\Api\Application;

use App\Models\Webhook;

class WebhookDeliveryTransformer extends BaseTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return 'webhook_delivery';
    }

    /**
     * @param  Webhook  $model
     */
    public function transform($model): array
    {
        return [
            'id' => $model->id,
            'webhook_configuration_id' => $model->webhook_configuration_id,
            'event' => $model->event,
            'endpoint' => $model->endpoint,
            'payload' => $model->payload,
            'successful' => $model->successful_at !== null,
            'successful_at' => $model->successful_at?->toAtomString(),
            'created_at' => $model->created_at?->toAtomString(),
            'updated_at' => $model->updated_at?->toAtomString(),
        ];
    }
}
