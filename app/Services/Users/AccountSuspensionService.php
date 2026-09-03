<?php

namespace App\Services\Users;

use App\Exceptions\DisplayException;
use App\Facades\Activity;
use App\Jobs\RevokeSftpAccessJob;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AccountSuspensionService
{
    public function suspend(User $actor, User $user): User
    {
        throw_if($actor->is($user), new DisplayException(trans('admin/user.suspension.errors.cannot_suspend_self')));
        Gate::forUser($actor)->authorize('update', $user);
        throw_if($user->isSuspended(), new DisplayException(trans('admin/user.suspension.errors.already_suspended')));

        /** @var User $suspendedUser */
        $suspendedUser = DB::transaction(function () use ($user) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            throw_if($lockedUser->isSuspended(), new DisplayException(trans('admin/user.suspension.errors.already_suspended')));

            if ($lockedUser->isRootAdmin()) {
                $activeRootAdmins = User::query()
                    ->whereNull('suspended_at')
                    ->whereHas('roles', fn ($query) => $query->where('name', Role::ROOT_ADMIN))
                    ->lockForUpdate()
                    ->get(['users.id']);

                throw_if($activeRootAdmins->count() <= 1, new DisplayException(trans('admin/user.suspension.errors.last_root_admin')));
            }

            $lockedUser->forceFill([
                'suspended_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $this->deleteDatabaseSessions($lockedUser);

            return $lockedUser;
        });

        $this->revokeRemoteAccess($suspendedUser);

        Activity::event('user:suspension.created')
            ->actor($actor)
            ->subject($suspendedUser)
            ->log();

        return $suspendedUser;
    }

    public function unsuspend(User $actor, User $user): User
    {
        Gate::forUser($actor)->authorize('update', $user);

        /** @var User $unsuspendedUser */
        $unsuspendedUser = DB::transaction(function () use ($user) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            throw_if(!$lockedUser->isSuspended(), new DisplayException(trans('admin/user.suspension.errors.not_suspended')));

            $lockedUser->forceFill(['suspended_at' => null])->save();

            return $lockedUser;
        });

        Activity::event('user:suspension.lifted')
            ->actor($actor)
            ->subject($unsuspendedUser)
            ->log();

        return $unsuspendedUser;
    }

    private function deleteDatabaseSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = config('session.table', 'sessions');
        if (is_string($table) && $table !== '') {
            DB::table($table)->where('user_id', $user->id)->delete();
        }
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
