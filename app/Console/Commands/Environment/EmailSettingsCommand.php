<?php

namespace App\Console\Commands\Environment;

use App\Exceptions\PanelException;
use App\Traits\EnvironmentWriterTrait;
use Illuminate\Console\Command;

class EmailSettingsCommand extends Command
{
    use EnvironmentWriterTrait;

    protected $description = 'Set or update the email sending configuration for the Panel.';

    protected $signature = 'p:environment:mail
                            {--driver= : The mail driver to use.}
                            {--email= : Email address that messages from the Panel will originate from.}
                            {--from= : The name emails from the Panel will appear to be from.}
                            {--scheme=}
                            {--host=}
                            {--port=}
                            {--endpoint=}
                            {--username=}
                            {--password=}';

    /** @var array<array-key, mixed> */
    protected array $variables = [];

    /**
     * Handle command execution.
     *
     * @throws PanelException
     */
    public function handle(): void
    {
        $this->variables['MAIL_MAILER'] = $this->option('driver') ?? $this->choice(
            trans('command/messages.environment.mail.ask_driver'),
            [
                'log' => 'Log',
                'smtp' => 'SMTP Server',
                'sendmail' => 'sendmail Binary',
                'mailgun' => 'Mailgun',
                'mandrill' => 'Mandrill',
                'postmark' => 'Postmark',
            ],
            env('MAIL_MAILER', env('MAIL_DRIVER', 'smtp')),
        );

        $method = 'setup' . studly_case($this->variables['MAIL_MAILER']) . 'DriverVariables';
        if (method_exists($this, $method)) {
            $this->{$method}();
        }

        $this->variables['MAIL_FROM_ADDRESS'] = $this->option('email') ?? $this->ask(
            trans('command/messages.environment.mail.ask_mail_from'),
            config('mail.from.address')
        );

        $this->variables['MAIL_FROM_NAME'] = $this->option('from') ?? $this->ask(
            trans('command/messages.environment.mail.ask_mail_name'),
            config('mail.from.name')
        );

        $this->writeToEnvironment($this->variables);

        $this->call('queue:restart');

        $this->line('Updating stored environment configuration file.');
        $this->line('');
    }

    /**
     * Handle variables for SMTP driver.
     */
    private function setupSmtpDriverVariables(): void
    {
        $this->variables['MAIL_HOST'] = $this->option('host') ?? $this->ask(
            trans('command/messages.environment.mail.ask_smtp_host'),
            config('mail.mailers.smtp.host')
        );

        $this->variables['MAIL_PORT'] = $this->option('port') ?? $this->ask(
            trans('command/messages.environment.mail.ask_smtp_port'),
            config('mail.mailers.smtp.port')
        );

        $this->variables['MAIL_USERNAME'] = $this->option('username') ?? $this->ask(
            trans('command/messages.environment.mail.ask_smtp_username'),
            config('mail.mailers.smtp.username')
        );

        $this->variables['MAIL_PASSWORD'] = $this->option('password') ?? $this->secret(
            trans('command/messages.environment.mail.ask_smtp_password')
        );

        $this->variables['MAIL_SCHEME'] = $this->option('scheme') ?? $this->choice(
            trans('command/messages.environment.mail.ask_scheme'),
            ['smtp' => 'SMTP', 'smtps' => 'SMTPS'],
            config('mail.mailers.smtp.scheme')
        );
    }

    /**
     * Handle variables for mailgun driver.
     */
    private function setupMailgunDriverVariables(): void
    {
        $this->variables['MAILGUN_DOMAIN'] = $this->option('host') ?? $this->ask(
            trans('command/messages.environment.mail.ask_mailgun_domain'),
            config('services.mailgun.domain')
        );

        $this->variables['MAILGUN_SECRET'] = $this->option('password') ?? $this->ask(
            trans('command/messages.environment.mail.ask_mailgun_secret'),
            config('services.mailgun.secret')
        );

        $this->variables['MAILGUN_ENDPOINT'] = $this->option('endpoint') ?? $this->ask(
            trans('command/messages.environment.mail.ask_mailgun_endpoint'),
            config('services.mailgun.endpoint')
        );
    }

    /**
     * Handle variables for mandrill driver.
     */
    private function setupMandrillDriverVariables(): void
    {
        $this->variables['MANDRILL_SECRET'] = $this->option('password') ?? $this->ask(
            trans('command/messages.environment.mail.ask_mandrill_secret'),
            config('services.mandrill.secret')
        );
    }

    /**
     * Handle variables for postmark driver.
     */
    private function setupPostmarkDriverVariables(): void
    {
        $this->variables['MAIL_DRIVER'] = 'smtp';
        $this->variables['MAIL_HOST'] = 'smtp.postmarkapp.com';
        $this->variables['MAIL_PORT'] = 587;
        $this->variables['MAIL_USERNAME'] = $this->variables['MAIL_PASSWORD'] = $this->option('username') ?? $this->ask(
            trans('command/messages.environment.mail.ask_postmark_username'),
            config('mail.username')
        );
    }
}
