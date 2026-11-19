<?php

namespace App\Filament\Server\Resources\Webhooks\Pages;

use App\Filament\Server\Resources\Webhooks\WebhookResource;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Resources\Pages\ListRecords;

class ListWebhooks extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = WebhookResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
