<?php

namespace App\Filament\Admin\Resources\Webhooks\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Pages\BaseAdminEditRecord;
use App\Filament\Admin\Resources\Webhooks\WebhookResource;
use App\Filament\Components\Actions\LoggedDeleteAction;
use App\Models\WebhookConfiguration;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\MutatesWebhookFormData;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

class EditWebhookConfiguration extends BaseAdminEditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use MutatesWebhookFormData;

    protected static string $resource = WebhookResource::class;

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            LoggedDeleteAction::make(),
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
        parent::afterSave();

        $this->dispatch('refresh-widget');
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);
        WebhookResource::sendHelpBanner();
    }
}
