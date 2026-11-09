<?php

namespace App\Notifications;

use App\Models\Server;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RemovedFromServer extends Notification implements ShouldQueue
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
            ->subject(trans('mail.removed_from_server.subject', locale: $locale))
            ->error()
            ->greeting(trans('mail.greeting', ['name' => $notifiable->username], $locale))
            ->line(trans('mail.removed_from_server.body', locale: $locale))
            ->line(trans('mail.removed_from_server.server_name', ['name' => $this->server->name], $locale))
            ->action(trans('mail.removed_from_server.action', locale: $locale), url(''));
    }
}
