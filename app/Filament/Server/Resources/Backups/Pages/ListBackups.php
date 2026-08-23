<?php

namespace App\Filament\Server\Resources\Backups\Pages;

use App\Enums\ServerUserSettingKey;
use App\Enums\TablerIcon;
use App\Filament\Server\Resources\Backups\BackupResource;
use App\Models\Server;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBackups extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = BackupResource::class;

    /** @return Action[] */
    protected function getDefaultHeaderActions(): array
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return [
            Action::make('notification_settings')
                ->label(trans('server/backup.notifications.action'))
                ->icon(TablerIcon::Bell)
                ->schema([
                    Toggle::make(ServerUserSettingKey::ManualBackupNotifications->value)
                        ->label(trans('server/backup.notifications.toggle_manual'))
                        ->helperText(trans('server/backup.notifications.helper_manual'))
                        ->default(fn () => (bool) user()?->getServerSetting($server, ServerUserSettingKey::ManualBackupNotifications)),
                    Toggle::make(ServerUserSettingKey::ScheduledBackupNotifications->value)
                        ->label(trans('server/backup.notifications.toggle_scheduled'))
                        ->helperText(trans('server/backup.notifications.helper_scheduled'))
                        ->default(fn () => (bool) user()?->getServerSetting($server, ServerUserSettingKey::ScheduledBackupNotifications)),
                ])
                ->action(function (array $data) use ($server) {
                    foreach (ServerUserSettingKey::cases() as $key) {
                        user()?->updateServerSetting($server, $key, (bool) $data[$key->value]);
                    }

                    Notification::make()
                        ->title(trans('server/backup.notifications.saved'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return trans('server/backup.title');
    }
}
