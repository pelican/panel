<?php

namespace App\Policies;

use App\Enums\RolePermissionModels;
use App\Enums\SubuserPermission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class BackupPolicy
{
    /**
     * Outside a server (the admin panel) backups are listed so servers can be
     * created from them, which needs the "create server" permission.
     */
    public function viewAny(User $user): bool
    {
        $tenant = Filament::getTenant();
        if (!$tenant) {
            return $user->can(RolePermissionModels::Server->create());
        }

        return $user->can(SubuserPermission::BackupRead, $tenant);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        $tenant = Filament::getTenant();
        if (!$tenant) {
            return false;
        }

        return $user->can(SubuserPermission::BackupCreate, $tenant);
    }

    public function delete(User $user, Model $model): bool
    {
        $tenant = Filament::getTenant();
        if (!$tenant) {
            return $user->can(RolePermissionModels::Server->create()) && $user->can(RolePermissionModels::Server->delete());
        }

        return $user->can(SubuserPermission::BackupDelete, $tenant);
    }
}
