<?php

namespace App\Jobs;

use App\Enums\SuspendAction;
use App\Models\Server;
use App\Models\User;
use App\Models\UserSuspension;
use App\Models\UserSuspensionServer;
use App\Services\Servers\SuspensionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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
        $userId = UserSuspension::query()->whereKey($this->suspensionId)->value('user_id');
        if (!$userId) {
            return;
        }

        $expectedStatus = $this->unsuspend
            ? UserSuspensionServer::STATUS_SUSPENDED
            : UserSuspensionServer::STATUS_PENDING;

        $failure = null;

        foreach (UserSuspensionServer::query()
            ->where('user_suspension_id', $this->suspensionId)
            ->where('status', $expectedStatus)
            ->lazyById() as $record) {
            try {
                $this->processRecord($record->id, (int) $userId, $expectedStatus, $service);
            } catch (Throwable $exception) {
                $failure ??= $exception;
            }
        }

        if ($failure) {
            throw $failure;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $expectedStatus = $this->unsuspend
            ? UserSuspensionServer::STATUS_SUSPENDED
            : UserSuspensionServer::STATUS_PENDING;

        UserSuspensionServer::query()
            ->where('user_suspension_id', $this->suspensionId)
            ->where('status', $expectedStatus)
            ->update([
                'status' => UserSuspensionServer::STATUS_FAILED,
                'error' => $exception?->getMessage() ?? 'The server operation failed after all retry attempts.',
            ]);
    }

    private function processRecord(int $recordId, int $userId, string $expectedStatus, SuspensionService $service): void
    {
        try {
            DB::transaction(function () use ($recordId, $userId, $expectedStatus, $service) {
                $user = User::query()->lockForUpdate()->find($userId);
                $suspension = UserSuspension::query()->lockForUpdate()->find($this->suspensionId);
                if (!$user || !$suspension || $suspension->user_id !== $user->id) {
                    return;
                }

                $stateMatches = $this->unsuspend
                    ? !is_null($suspension->lifted_at) && !$user->isSuspended()
                    : is_null($suspension->lifted_at) && $user->isSuspended();

                if (!$stateMatches) {
                    return;
                }

                $record = UserSuspensionServer::query()
                    ->where('user_suspension_id', $suspension->id)
                    ->where('status', $expectedStatus)
                    ->lockForUpdate()
                    ->find($recordId);

                if (!$record) {
                    return;
                }

                $server = $record->server()->lockForUpdate()->first();
                if (!$server || $server->owner_id !== $suspension->user_id) {
                    $record->update([
                        'status' => UserSuspensionServer::STATUS_SKIPPED,
                        'error' => 'The server no longer belongs to this user.',
                    ]);

                    return;
                }

                $server->load('transfer');

                if ($this->unsuspend) {
                    if (!$server->isSuspended()) {
                        $record->update([
                            'status' => UserSuspensionServer::STATUS_SKIPPED,
                            'error' => 'The server is no longer suspended.',
                        ]);

                        return;
                    }

                    $this->apply($record, $server, $service, SuspendAction::Unsuspend);

                    return;
                }

                if (!is_null($server->status) || !is_null($server->transfer)) {
                    $record->update([
                        'status' => UserSuspensionServer::STATUS_SKIPPED,
                        'error' => 'The server entered a conflicting state before it could be suspended.',
                    ]);

                    return;
                }

                $this->apply($record, $server, $service, SuspendAction::Suspend);
            }, 5);
        } catch (Throwable $exception) {
            UserSuspensionServer::query()
                ->whereKey($recordId)
                ->where('status', $expectedStatus)
                ->update(['error' => $exception->getMessage()]);

            throw $exception;
        }
    }

    private function apply(UserSuspensionServer $record, Server $server, SuspensionService $service, SuspendAction $action): void
    {
        $service->handle($server, $action);
        $record->update($action === SuspendAction::Suspend ? [
            'status' => UserSuspensionServer::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'error' => null,
        ] : [
            'status' => UserSuspensionServer::STATUS_UNSUSPENDED,
            'unsuspended_at' => now(),
            'error' => null,
        ]);
    }
}
