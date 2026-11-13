<?php

namespace App\Console\Commands\Environment;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class QueueWorkerServiceCommand extends Command
{
    protected $description = 'Create the service for the queue worker.';

    protected $signature = 'p:environment:queue-service
        {--service-name= : Name of the queue worker service.}
        {--user= : The user that PHP runs under.}
        {--group= : The group that PHP runs under.}
        {--overwrite : Force overwrite if the service file already exists.}';

    public function handle(): int
    {
        if (is_running_in_docker()) {
            $result = Process::run('supervisorctl restart queue-worker');
            if ($result->failed()) {
                $this->error('Error restarting service: ' . $result->errorOutput());

                return self::FAILURE;
            }
            $this->line('Queue worker service file updated successfully.');

            return self::SUCCESS;
        }
        $serviceName = $this->option('service-name') ?? $this->ask('Queue worker service name', 'pelican-queue');
        $path = '/etc/systemd/system/' . $serviceName  . '.service';

        $fileExists = @file_exists($path);
        if ($fileExists && !$this->option('overwrite') && !$this->confirm('The service file already exists. Do you want to overwrite it?')) {
            $this->line('Creation of queue worker service file aborted because service file already exists.');

            return self::SUCCESS;
        }

        $user = $this->option('user') ?? $this->ask('Webserver User', 'www-data');
        $group = $this->option('group') ?? $this->ask('Webserver Group', 'www-data');

        $redisUsed = config('queue.default') === 'redis' || config('session.driver') === 'redis' || config('cache.default') === 'redis';
        $afterRedis = $redisUsed ? '
After=redis-server.service' : '';

        $basePath = base_path();

        $success = File::put($path, "# Pelican Queue File
# ----------------------------------

[Unit]
Description=Pelican Queue Service$afterRedis

[Service]
User=$user
Group=$group
Restart=always
ExecStart=/usr/bin/php $basePath/artisan queue:work --tries=3
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
        ");

        if (!$success) {
            $this->error('Error creating service file');

            return self::FAILURE;
        }

        if ($fileExists) {
            $result = Process::run("systemctl restart $serviceName.service");
            if ($result->failed()) {
                $this->error('Error restarting service: ' . $result->errorOutput());

                return self::FAILURE;
            }

            $this->line('Queue worker service file updated successfully.');
        } else {
            $result = Process::run("systemctl enable --now $serviceName.service");
            if ($result->failed()) {
                $this->error('Error enabling service: ' . $result->errorOutput());

                return self::FAILURE;
            }

            $this->line('Queue worker service file created successfully.');
        }

        return self::SUCCESS;
    }
}
