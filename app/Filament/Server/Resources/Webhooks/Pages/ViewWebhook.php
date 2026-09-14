<?php

namespace App\Filament\Server\Resources\Webhooks\Pages;

use App\Filament\Server\Resources\Webhooks\WebhookResource;
use App\Traits\Filament\MutatesWebhookFormData;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWebhook extends ViewRecord
{
    use MutatesWebhookFormData;

    protected static string $resource = WebhookResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->mutateWebhookDataBeforeFill($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
