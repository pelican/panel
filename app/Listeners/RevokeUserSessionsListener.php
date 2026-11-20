<?php

namespace App\Listeners;

use App\Events\User\PasswordChanged;
use App\Models\User;
use App\Services\Users\UserSessionService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;

/**
 * Signs out a user's other devices when their password is changed or reset.
 */
class RevokeUserSessionsListener
{
    public function __construct(
        private readonly UserSessionService $service,
        private readonly Request $request,
    ) {}

    public function handle(PasswordChanged|PasswordReset $event): void
    {
        $user = $event->user;
        if (!$user instanceof User) {
            return;
        }

        // Keep the device making the change signed in when the user changed their own password.
        $current = $this->request->user()?->is($user) ? $this->service->current($this->request, $user) : null;

        $this->service->revokeOthers($user, $current);
    }
}
