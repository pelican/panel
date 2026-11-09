<?php

namespace App\Notifications;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $token = null) {}

    /** @return string[] */
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $locale = $notifiable->language ?? 'en';

        $message = (new MailMessage())
            ->subject(trans('mail.account_created.subject', locale: $locale))
            ->greeting(trans('mail.greeting', ['name' => $notifiable->username], $locale))
            ->line(trans('mail.account_created.body', ['app' => config('app.name')], $locale))
            ->line(trans('mail.account_created.username', ['username' => $notifiable->username], $locale))
            ->line(trans('mail.account_created.email', ['email' => $notifiable->email], $locale));

        if (!is_null($this->token)) {
            return $message->action(trans('mail.account_created.action', locale: $locale), Filament::getPanel('app')->getResetPasswordUrl($this->token, $notifiable));
        }

        return $message;
    }
}
