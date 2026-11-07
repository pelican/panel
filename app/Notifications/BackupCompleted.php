<?php

namespace App\Notifications;

use App\Filament\Server\Resources\Backups\Pages\ListBackups;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Backup $backup) {}

    /** @return string[] */
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $locale = $notifiable->language ?? 'en';

        return (new MailMessage())
            ->subject(trans('mail.backup_completed.subject_' . ($this->backup->is_successful ? 'success' : 'failed'), locale: $locale))
            ->greeting(trans('mail.greeting', ['name' => $notifiable->username], $locale))
            ->line(trans('mail.backup_completed.body_' . ($this->backup->is_successful ? 'success' : 'failed'), locale: $locale))
            ->line(trans('mail.backup_completed.backup_name', ['name' => $this->backup->name], $locale))
            ->line(trans('mail.backup_completed.server_name', ['name' => $this->backup->server->name], $locale))
            ->action(trans('mail.backup_completed.action', locale: $locale), ListBackups::getUrl(panel: 'server', tenant: $this->backup->server));
    }
}
