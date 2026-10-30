<?php

namespace App\Http\Requests\Api\Application\Users\Concerns;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait ProtectsRootAdmins
{
    /**
     * Root admins are never editable through the application API, regardless of who owns the key.
     */
    protected function denyIfTargetIsRootAdmin(): void
    {
        throw_if(
            $this->parameter('user', User::class)->isRootAdmin(),
            new AccessDeniedHttpException('Root admins cannot be edited via the application API.')
        );
    }
}
