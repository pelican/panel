<?php

namespace App\Filament\Server\Resources\Webhooks\Pages;

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

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('tabler-trash'),
            Action::make('test_now')
                ->label(trans('admin/webhook.test_now'))
                ->color('primary')
                ->icon('tabler-send')
                ->action(fn (WebhookConfiguration $record) => $record->run())
                ->tooltip(trans('admin/webhook.test_now_help')),
            $this->getSaveFormAction()->formId('form')->icon('tabler-device-floppy'),
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
