<?php

namespace App\Notifications;

use App\Filament\Server\Pages\Console;
use App\Models\Server;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServerInstalled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Server $server) {}

    /** @return string[] */
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $locale = $notifiable->language ?? 'en';

        return (new MailMessage())
            ->subject(trans('mail.server_installed.subject', locale: $locale))
            ->greeting(trans('mail.greeting', ['name' => $notifiable->username], $locale))
            ->line(trans('mail.server_installed.body', locale: $locale))
            ->line(trans('mail.server_installed.server_name', ['name' => $this->server->name], $locale))
            ->action(trans('mail.server_installed.action', locale: $locale), Console::getUrl(panel: 'server', tenant: $this->server));
    }
}
