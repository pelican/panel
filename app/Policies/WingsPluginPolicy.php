<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WingsPlugin;

class WingsPluginPolicy
{
    use DefaultAdminPolicies;

    protected string $modelName = 'plugin';

    public function before(User $user, string $ability, string|WingsPlugin $plugin): ?bool
    {
        // For "viewAny" the $plugin param is the class name
        if (is_string($plugin)) {
            return null;
        }

        $node = $plugin->node();
        if (!$node || !$user->canTarget($node)) {
            return false;
        }

        return null;
    }
}
