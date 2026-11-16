<?php

namespace App\Filament\Admin\Resources\Webhooks\Pages;

use App\Enums\WebhookScope;
use App\Filament\Admin\Resources\Webhooks\WebhookResource;
use App\Models\WebhookConfiguration;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListWebhookConfigurations extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = WebhookResource::class;

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    public function getTabs(): array
    {
        return collect(WebhookScope::cases())
            ->mapWithKeys(fn (WebhookScope $scope) => [
                $scope->value . '-webhooks' => Tab::make(trans('admin/webhook.tabs.' . $scope->value))
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('scope', $scope))
                    ->badge(WebhookConfiguration::where('scope', $scope)->count()),
            ])
            ->all();
    }
}
