<?php

namespace App\Console\Commands\Server;

use App\Enums\ServerTransferBatchStatus;
use App\Jobs\AdvanceServerTransferBatch;
use App\Models\ServerTransferBatch;
use Illuminate\Console\Command;

class AdvanceTransferBatchesCommand extends Command
{
    protected $signature = 'p:server:advance-transfer-batches';

    protected $description = 'Wake up running server transfer batches so that a missed daemon callback or an unreachable node cannot stall them.';

    public function handle(): void
    {
        ServerTransferBatch::query()
            ->where('status', ServerTransferBatchStatus::Running)
            ->each(fn (ServerTransferBatch $batch) => AdvanceServerTransferBatch::dispatch($batch));
    }
}
