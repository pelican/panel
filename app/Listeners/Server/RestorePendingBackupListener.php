<?php

namespace App\Listeners\Server;

use App\Events\Server\Installed;
use App\Models\ServerRestore;
use App\Services\Backups\ServerRestoreService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Starts restoring the backup a server was created from once the node has
 * finished installing it.
 */
class RestorePendingBackupListener implements ShouldQueue
{
    public function __construct(private readonly ServerRestoreService $restoreService) {}

    public function handle(Installed $event): void
    {
        /** @var ServerRestore|null $restore */
        $restore = ServerRestore::query()
            ->where('server_id', $event->server->id)
            ->where('status', ServerRestore::STATUS_PENDING)
            ->latest('id')
            ->first();

        if (!$restore) {
            return;
        }

        if (!$event->successful) {
            $this->restoreService->fail($restore, trans('admin/backup.restore.install_failed'));

            return;
        }

        $this->restoreService->start($restore);
    }
}
