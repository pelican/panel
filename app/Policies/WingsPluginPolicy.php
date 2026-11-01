<?php

namespace App\Policies;

class WingsPluginPolicy
{
    use DefaultAdminPolicies;

    protected string $modelName = 'plugin';
}
