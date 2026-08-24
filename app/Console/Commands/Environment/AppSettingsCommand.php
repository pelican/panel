<?php

namespace App\Console\Commands\Environment;

use App\Traits\EnvironmentWriterTrait;
use Exception;
use Illuminate\Console\Command;

class AppSettingsCommand extends Command
{
    use EnvironmentWriterTrait;

    protected $description = 'Configure basic environment settings for the Panel.';

    protected $signature = 'p:environment:setup
                            {--url= : The URL that this Panel is running on.}';

    public function handle(): int
    {
        $path = base_path('.env');
        if (!file_exists($path)) {
            $this->comment('Copying example .env file');

            if (!copy($path . '.example', $path)) {
                return 1;
            }
        }

        if (!$this->handleAppUrl()) {
            return 1;
        }

        if (!config('app.key')) {
            $this->comment('Generating app key');
            $return = $this->call('key:generate');
            if ($return !== 0) {
                return $return;
            }
        }

        $this->comment('Creating storage link');
        $return = $this->call('storage:link');
        if ($return !== 0) {
            return $return;
        }

        $this->comment('Caching components & icons');
        $return = $this->call('filament:optimize');
        if ($return !== 0) {
            return $return;
        }

        return 0;
    }

    private function handleAppUrl(): bool
    {
        $appUrl = $this->option('url');

        if (blank($appUrl) && $this->input->isInteractive()) {
            $appUrl = $this->ask('Application URL', config('app.url'));
        }

        if (blank($appUrl)) {
            $this->error('Application URL is required.');

            return false;
        }

        if (!str_starts_with($appUrl, 'http://') && !str_starts_with($appUrl, 'https://')) {
            $this->error('Application URL need to start with either http:// or https://.');

            return false;
        }

        try {
            $this->comment('Writing APP_URL to .env file');
            $this->writeToEnvironment(['APP_URL' => $appUrl]);

            return true;
        } catch (Exception $exception) {
            $this->error('Could not write .env file: ' . $exception->getMessage());

            return false;
        }
    }
}
