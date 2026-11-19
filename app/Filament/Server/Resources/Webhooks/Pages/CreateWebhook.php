<?php

namespace App\Filament\Server\Resources\Webhooks\Pages;

use App\Enums\TablerIcon;
use App\Enums\WebhookScope;
use App\Filament\Server\Resources\Webhooks\WebhookResource;
use App\Models\Server;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\MutatesWebhookFormData;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateWebhook extends CreateRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use MutatesWebhookFormData;

    protected static string $resource = WebhookResource::class;

    protected static bool $canCreateAnother = false;

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            Action::make('create')
                ->hiddenLabel()
                ->action('create')
                ->keyBindings(['mod+s'])
                ->tooltip(trans('filament-panels::resources/pages/create-record.form.actions.create.label'))
                ->icon(TablerIcon::FilePlus),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $server = Filament::getTenant();
        abort_unless($server instanceof Server, 403);

        $data['server_id'] = $server->id;
        $data['scope'] = WebhookScope::Server;

        return $this->mutateWebhookDataBeforeSave($data);
    }

    protected function getRedirectUrl(): string
    {
        return EditWebhook::getUrl(['record' => $this->getRecord()]);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        parent::mount();
        WebhookResource::sendHelpBanner();
    }
}
