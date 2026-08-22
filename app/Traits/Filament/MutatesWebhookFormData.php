<?php

namespace App\Traits\Filament;

use App\Facades\WebhookTypes;

/**
 * Hands the form state to the schema that owns the selected webhook type, so every
 * create and edit page collapses and expands the payload the same way.
 */
trait MutatesWebhookFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateWebhookDataBeforeSave(array $data): array
    {
        if ($schema = WebhookTypes::get($data['type'] ?? null)) {
            return $schema->mutateFormDataBeforeSave($data);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateWebhookDataBeforeFill(array $data): array
    {
        if ($schema = WebhookTypes::get($data['type'] ?? null)) {
            return $schema->mutateFormDataBeforeFill($data);
        }

        return $data;
    }
}
