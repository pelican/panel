<?php

namespace App\Jobs;

use App\Models\ServerTransferBatch;
use App\Services\Servers\ServerTransferBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class AdvanceServerTransferBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public ServerTransferBatch $batch) {}

    /**
     * Overlapping runs are dropped rather than released: whatever they would have
     * picked up is seen by the next wake-up, at the latest the scheduled one.
     *
     * @return array<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->batch->id))->dontRelease()->expireAfter(120)];
    }

    public function handle(ServerTransferBatchService $service): void
    {
        if ($service->advance($this->batch)) {
            self::dispatch($this->batch)->delay(now()->addSeconds(5));
        }
    }
}
