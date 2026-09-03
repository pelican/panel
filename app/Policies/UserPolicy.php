<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy
{
    use DefaultAdminPolicies {
        update as defaultUpdate;
        delete as defaultDelete;
    }

    protected string $modelName = 'user';

    public function update(User $user, Model $model): bool
    {
        return $user->canTarget($model) && $this->defaultUpdate($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->canTarget($model) && $this->defaultDelete($user, $model);
    }
}
