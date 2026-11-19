<?php

namespace App\Filament\Admin\Resources\Webhooks\Pages;

use App\Filament\Admin\Resources\Webhooks\WebhookResource;
use App\Models\Server;
use App\Models\WebhookConfiguration;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\MutatesWebhookFormData;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWebhookConfiguration extends ViewRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use MutatesWebhookFormData;

    protected static string $resource = WebhookResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->mutateWebhookDataBeforeFill($data);
    }

    public function getSubheading(): ?string
    {
        /** @var WebhookConfiguration $webhook */
        $webhook = $this->getRecord();
        /** @var Server|null $server */
        $server = $webhook->server;

        return $server ? trans('admin/webhook.server') . ': ' . $server->name : null;
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
