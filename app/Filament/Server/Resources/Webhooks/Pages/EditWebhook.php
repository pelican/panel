<?php

namespace App\Filament\Server\Resources\Webhooks\Pages;

use App\Enums\TablerIcon;
use App\Filament\Server\Resources\Webhooks\WebhookResource;
use App\Models\WebhookConfiguration;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\MutatesWebhookFormData;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWebhook extends EditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use MutatesWebhookFormData;

    protected static string $resource = WebhookResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('test_now')
                ->hiddenLabel()
                ->tooltip(trans('admin/webhook.test_now'))
                ->color('primary')
                ->disabled(fn (WebhookConfiguration $webhookConfiguration) => count($webhookConfiguration->events) === 0)
                ->action(fn (WebhookConfiguration $webhookConfiguration) => $webhookConfiguration->run())
                ->icon(TablerIcon::TestPipe),
            Action::make('save')
                ->hiddenLabel()
                ->action('save')
                ->keyBindings(['mod+s'])
                ->tooltip(trans('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->icon(TablerIcon::DeviceFloppy),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mutateWebhookDataBeforeSave($data);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->mutateWebhookDataBeforeFill($data);
    }
}
