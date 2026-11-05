<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    public string $mail_mailer;

    public ?string $mail_from_address;

    public ?string $mail_from_name;

    public ?string $mail_host;

    public ?int $mail_port;

    public ?string $mail_username;

    public ?string $mail_password;

    public ?string $mail_scheme;

    public ?string $mailgun_domain;

    public ?string $mailgun_secret;

    public ?string $mailgun_endpoint;

    public static function group(): string
    {
        return 'mail';
    }

    /** @return string[] */
    public static function encrypted(): array
    {
        return ['mail_password', 'mailgun_secret'];
    }
}
