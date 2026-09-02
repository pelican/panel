<?php

namespace App\Services\Users;

use App\Enums\ServerState;
use App\Exceptions\DisplayException;
use App\Facades\Activity;
use App\Jobs\ProcessUserSuspensionServersJob;
use App\Jobs\RevokeSftpAccessJob;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSuspension;
use App\Models\UserSuspensionServer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AccountSuspensionService
{
    public function suspend(User $actor, User $user, string $reason, bool $suspendServers): UserSuspension
    {
        Gate::forUser($actor)->authorize('suspend', $user);

        $reason = trim($reason);
        throw_if($actor->is($user), new DisplayException('You cannot suspend your own account.'));
        throw_if($user->isSuspended(), new DisplayException('This account is already suspended.'));
        throw_if($reason === '', new DisplayException('A suspension reason is required.'));

        $servers = $suspendServers ? $user->servers()->with('transfer')->get() : collect();
        foreach ($servers as $server) {
            Gate::forUser($actor)->authorize('update', $server);
        }

        /** @var UserSuspension $suspension */
        $suspension = DB::transaction(function () use ($actor, $user, $reason, $suspendServers, $servers) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            throw_if($lockedUser->isSuspended(), new DisplayException('This account is already suspended.'));

            if ($lockedUser->isRootAdmin()) {
                $activeRootAdmins = User::query()
                    ->whereNull('suspended_at')
                    ->whereHas('roles', fn ($query) => $query->where('name', Role::ROOT_ADMIN))
                    ->lockForUpdate()
                    ->get(['users.id']);

                throw_if($activeRootAdmins->count() <= 1, new DisplayException('The last active root administrator cannot be suspended.'));
            }

            $suspension = $lockedUser->suspensions()->create([
                'actor_id' => $actor->id,
                'reason' => $reason,
                'suspend_servers' => $suspendServers,
            ]);

            $lockedUser->forceFill([
                'suspended_at' => now(),
                'auth_session_version' => $lockedUser->auth_session_version + 1,
                'remember_token' => Str::random(60),
            ])->save();

            foreach ($servers as $server) {
                $status = UserSuspensionServer::STATUS_PENDING;
                $error = null;

                if ($server->status === ServerState::Suspended) {
                    $status = UserSuspensionServer::STATUS_SKIPPED;
                    $error = 'The server was already suspended.';
                } elseif (!is_null($server->status) || !is_null($server->transfer)) {
                    $status = UserSuspensionServer::STATUS_SKIPPED;
                    $error = 'The server is currently in a conflicting state.';
                }

                $suspension->servers()->create([
                    'server_id' => $server->id,
                    'status' => $status,
                    'error' => $error,
                ]);
            }

            return $suspension;
        });

        $this->revokeRemoteAccess($user);

        if ($suspendServers) {
            ProcessUserSuspensionServersJob::dispatch($suspension->id);
        }

        Activity::event('user:suspension.created')
            ->actor($actor)
            ->subject($user)
            ->property('reason', $reason)
            ->property('suspend_servers', $suspendServers)
            ->log();

        return $suspension;
    }

    public function unsuspend(User $actor, User $user, bool $unsuspendServers): UserSuspension
    {
        Gate::forUser($actor)->authorize('suspend', $user);

        /** @var UserSuspension $suspension */
        $suspension = DB::transaction(function () use ($actor, $user) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            throw_if(!$lockedUser->isSuspended(), new DisplayException('This account is not suspended.'));

            $suspension = $lockedUser->suspensions()->whereNull('lifted_at')->latest('id')->lockForUpdate()->firstOrFail();
            $suspension->update([
                'lifted_at' => now(),
                'lifted_by' => $actor->id,
            ]);

            $lockedUser->forceFill([
                'suspended_at' => null,
                'auth_session_version' => $lockedUser->auth_session_version + 1,
                'remember_token' => Str::random(60),
            ])->save();

            return $suspension;
        });

        if ($unsuspendServers) {
            ProcessUserSuspensionServersJob::dispatch($suspension->id, true);
        }

        Activity::event('user:suspension.lifted')
            ->actor($actor)
            ->subject($user)
            ->property('unsuspend_servers', $unsuspendServers)
            ->log();

        return $suspension;
    }

    private function revokeRemoteAccess(User $user): void
    {
        Node::query()
            ->whereIn('nodes.id', $user->directAccessibleServers()->select('servers.node_id')->distinct())
            ->chunk(50, function (Collection $nodes) use ($user) {
                $nodes->each(fn (Node $node) => RevokeSftpAccessJob::dispatch($user->uuid, $node));
            });
    }
}
