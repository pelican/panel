<?php

namespace App\Checks;

use App\Models\Role;
use App\Models\User;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

class AdminUserCheck extends Check
{
    /**
     * Verify that at least one root administrator account exists.
     */
    public function run(): Result
    {
        $result = Result::make()->meta([
            'remediation' => trans('installer.health.admin.remediation'),
        ]);

        try {
            return User::role(Role::ROOT_ADMIN)->exists()
                ? $result->ok(trans('installer.health.admin.passed'))
                : $result->failed(trans('installer.health.admin.failed'));
        } catch (Throwable $exception) {
            return $result->failed(trans('installer.health.admin.exception', [
                'error' => $exception->getMessage(),
            ]));
        }
    }
}
