<?php

namespace App\Jobs;

use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued so that acting on a whole node doesn't make the admin wait on the daemon once per server.
 */
class SendServerPowerAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Server $server, public string $signal) {}

    public function handle(DaemonServerRepository $repository): void
    {
        $repository->setServer($this->server)->power($this->signal);
    }
}
