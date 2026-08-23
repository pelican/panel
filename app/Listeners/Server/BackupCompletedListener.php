<?php

namespace App\Listeners\Server;

use App\Enums\ServerUserSettingKey;
use App\Events\Server\BackupCompleted;
use App\Filament\Server\Resources\Backups\Pages\ListBackups;
use App\Models\ServerUserSettings;
use App\Models\User;
use App\Notifications\BackupCompleted as BackupCompletedNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class BackupCompletedListener
{
    public function handle(BackupCompleted $event): void
    {
        $event->backup->loadMissing(['server.user', 'server.subusers.user']);

        $server = $event->backup->server;

        $candidates = collect([$server->user])
            ->concat($server->subusers->map->user)
            ->filter()
            ->unique('id');

        $settings = ServerUserSettings::query()
            ->where('server_id', $server->id)
            ->whereIn('user_id', $candidates->pluck('id'))
            ->get()
            ->keyBy('user_id');

        // Users only receive notifications if they opted in for this kind of backup.
        $key = $event->backup->is_scheduled ? ServerUserSettingKey::ScheduledBackupNotifications : ServerUserSettingKey::ManualBackupNotifications;

        $recipients = $candidates->filter(function (User $user) use ($settings, $key) {
            $userSettings = $settings->get($user->id)->settings ?? [];

            return (bool) ($userSettings[$key->value] ?? $key->getDefaultValue());
        });

        foreach ($recipients as $user) {
            $locale = $user->language ?? 'en';

            Notification::make()
                ->status($event->backup->is_successful ? 'success' : 'danger')
                ->title(trans('notifications.backup_' . ($event->backup->is_successful ? 'completed' : 'failed'), locale: $locale))
                ->body(trans('notifications.backup_body', ['name' => $event->backup->name, 'server' => $server->name], $locale))
                ->actions([
                    Action::make('exclude_view')
                        ->button()
                        ->label(trans('notifications.view_backups', locale: $locale))
                        ->markAsRead()
                        ->url(fn () => ListBackups::getUrl(panel: 'server', tenant: $server)),
                ])
                ->sendToDatabase($user);

            $user->notify(new BackupCompletedNotification($event->backup));
        }
    }
}
