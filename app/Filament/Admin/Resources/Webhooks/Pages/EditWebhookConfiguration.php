<?php

namespace App\Filament\Admin\Resources\Webhooks\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Webhooks\WebhookResource;
use App\Models\WebhookConfiguration;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\MutatesWebhookFormData;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWebhookConfiguration extends EditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use MutatesWebhookFormData;

    protected static string $resource = WebhookResource::class;

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('test_now')
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

    protected function afterSave(): void
    {
        $this->dispatch('refresh-widget');
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);
        WebhookResource::sendHelpBanner();
    }
}
