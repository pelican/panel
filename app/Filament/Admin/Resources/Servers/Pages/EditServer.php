<?php

namespace App\Filament\Admin\Resources\Servers\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Servers\ServerResource;
use App\Filament\Server\Pages\Console;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Servers\ServerDeletionService;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\CanCustomizeTabs;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Http\Client\ConnectionException;
use Random\RandomException;

class EditServer extends EditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use CanCustomizeTabs;

    protected static string $resource = ServerResource::class;

    private DaemonServerRepository $daemonServerRepository;

    public function boot(DaemonServerRepository $daemonServerRepository): void
    {
        $this->daemonServerRepository = $daemonServerRepository;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->persistTabInQueryString()
                    ->columns([
                        'default' => 2,
                        'sm' => 2,
                        'md' => 4,
                        'lg' => 6,
                    ])
                    ->columnSpanFull()
                    ->tabs($this->getTabs()),
            ]);
    }

    /**
     * @return Tab[]
     *
     * @throws RandomException
     */
    protected function getDefaultTabs(): array
    {
        return ServerResource::detailTabs();
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        /** @var Server $server */
        $server = $this->getRecord();

        $canForceDelete = cache()->get("servers.$server->uuid.canForceDelete", false);

        return [
            Action::make('Delete')
                ->color('danger')
                ->hiddenLabel()
                ->tooltip(trans('filament-actions::delete.single.label'))
                ->modalHeading(trans('filament-actions::delete.single.modal.heading', ['label' => $this->getRecordTitle()]))
                ->modalSubmitActionLabel(trans('filament-actions::delete.single.label'))
                ->requiresConfirmation()
                ->action(function (Server $server, ServerDeletionService $service) {
                    try {
                        $service->handle($server);

                        return redirect(ListServers::getUrl(panel: 'admin'));
                    } catch (ConnectionException) {
                        cache()->put("servers.$server->uuid.canForceDelete", true, now()->addMinutes(5));

                        return Notification::make()
                            ->title(trans('admin/server.notifications.error_server_delete'))
                            ->body(trans('admin/server.notifications.error_server_delete_body'))
                            ->color('warning')
                            ->icon(TablerIcon::Database)
                            ->warning()
                            ->send();
                    }
                })
                ->hidden(fn () => $canForceDelete)
                ->authorize(fn (Server $server) => user()?->can('delete server', $server))
                ->icon(TablerIcon::Trash),
            Action::make('exclude_force_delete')
                ->color('danger')
                ->label(trans('filament-actions::force-delete.single.label'))
                ->modalHeading(trans('filament-actions::force-delete.single.modal.heading', ['label' => $this->getRecordTitle()]))
                ->modalSubmitActionLabel(trans('filament-actions::force-delete.single.label'))
                ->requiresConfirmation()
                ->action(function (Server $server, ServerDeletionService $service) {
                    try {
                        $service->withForce()->handle($server);

                        return redirect(ListServers::getUrl(panel: 'admin'));
                    } catch (ConnectionException) {
                        return cache()->forget("servers.$server->uuid.canForceDelete");
                    }
                })
                ->visible(fn () => $canForceDelete)
                ->authorize(fn (Server $server) => user()?->can('delete server', $server)),
            Action::make('console')
                ->hiddenLabel()
                ->tooltip(trans('admin/server.console'))
                ->icon(TablerIcon::Terminal)
                ->url(fn (Server $server) => Console::getUrl(panel: 'server', tenant: $server)),
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
        if (!isset($data['description'])) {
            $data['description'] = '';
        }

        unset($data['docker'], $data['status'], $data['allocation_id']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Server $server */
        $server = $this->record;

        $changed = collect($server->getChanges())->except(['updated_at', 'name', 'owner_id', 'condition', 'description', 'external_id', 'tags', 'cpu_pinning', 'allocation_limit', 'database_limit', 'backup_limit', 'skip_scripts'])->all();

        try {
            if ($changed) {
                $this->daemonServerRepository->setServer($server)->sync();
            }
            parent::getSavedNotification()?->send();
        } catch (ConnectionException) {
            Notification::make()
                ->title(trans('admin/server.notifications.error_connecting', ['node' => $server->node->name]))
                ->body(trans('admin/server.notifications.error_connecting_description'))
                ->color('warning')
                ->icon(TablerIcon::Database)
                ->warning()
                ->send();
        }
    }

    protected function getSavedNotification(): ?Notification
    {
        return null;
    }
}
