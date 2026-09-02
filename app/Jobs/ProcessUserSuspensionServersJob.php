<?php

namespace App\Jobs;

use App\Enums\SuspendAction;
use App\Models\UserSuspension;
use App\Models\UserSuspensionServer;
use App\Services\Servers\SuspensionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessUserSuspensionServersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $suspensionId,
        public readonly bool $unsuspend = false,
    ) {}

    public function handle(SuspensionService $service): void
    {
        $suspension = UserSuspension::query()->with('user')->find($this->suspensionId);
        if (!$suspension || (!$this->unsuspend && $suspension->lifted_at)) {
            return;
        }

        $query = $suspension->servers()->with('server');
        $query->where('status', $this->unsuspend ? UserSuspensionServer::STATUS_SUSPENDED : UserSuspensionServer::STATUS_PENDING);

        $query->lazyById()->each(function (UserSuspensionServer $record) use ($service, $suspension) {
            $server = $record->server;
            if (!$server || $server->owner_id !== $suspension->user_id) {
                $record->update([
                    'status' => UserSuspensionServer::STATUS_SKIPPED,
                    'error' => 'The server no longer belongs to this user.',
                ]);

                return;
            }

            if ($this->unsuspend) {
                if (!$server->isSuspended()) {
                    $record->update([
                        'status' => UserSuspensionServer::STATUS_SKIPPED,
                        'error' => 'The server is no longer suspended.',
                    ]);

                    return;
                }

                $this->apply($record, $service, SuspendAction::Unsuspend);

                return;
            }

            if (!is_null($server->status) || !is_null($server->transfer)) {
                $record->update([
                    'status' => UserSuspensionServer::STATUS_SKIPPED,
                    'error' => 'The server entered a conflicting state before it could be suspended.',
                ]);

                return;
            }

            $this->apply($record, $service, SuspendAction::Suspend);
        });
    }

    private function apply(UserSuspensionServer $record, SuspensionService $service, SuspendAction $action): void
    {
        try {
            $service->handle($record->server, $action);
            $record->update($action === SuspendAction::Suspend ? [
                'status' => UserSuspensionServer::STATUS_SUSPENDED,
                'suspended_at' => now(),
                'error' => null,
            ] : [
                'status' => UserSuspensionServer::STATUS_UNSUSPENDED,
                'unsuspended_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            $record->server->refresh();
            $changed = $action === SuspendAction::Suspend
                ? $record->server->isSuspended()
                : !$record->server->isSuspended();

            $record->update([
                'status' => $changed
                    ? ($action === SuspendAction::Suspend ? UserSuspensionServer::STATUS_SUSPENDED : UserSuspensionServer::STATUS_UNSUSPENDED)
                    : UserSuspensionServer::STATUS_FAILED,
                'suspended_at' => $action === SuspendAction::Suspend && $changed ? now() : $record->suspended_at,
                'unsuspended_at' => $action === SuspendAction::Unsuspend && $changed ? now() : null,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
