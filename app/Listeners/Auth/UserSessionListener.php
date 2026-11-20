<?php

namespace App\Listeners\Auth;

use App\Models\User;
use App\Services\Users\UserSessionService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserSessionListener
{
    public function __construct(
        private readonly UserSessionService $service,
        private readonly Request $request,
    ) {}

    public function handle(Login|Logout $event): void
    {
        if (!$event->user instanceof User || !$this->request->hasSession()) {
            return;
        }

        $guard = Auth::guard($event->guard);
        if (!$guard instanceof SessionGuard) {
            return;
        }

        if ($event instanceof Logout) {
            $this->service->forgetCurrent($this->request, $event->user);

            return;
        }

        // Remember-me logins are checked by the TrackUserSession middleware, which signs
        // the device out again if it was revoked.
        if ($guard->viaRemember()) {
            return;
        }

        // A password login: reuse this browser's device row if it has one, otherwise add one.
        $this->request->session()->forget(UserSessionService::SESSION_KEY);

        $session = $this->service->current($this->request, $event->user);
        if ($session && !$session->isInactive()) {
            $this->service->touch($this->request, $session, $event->remember);
            $session->forceFill(['remember' => $event->remember])->save();

            return;
        }

        $session?->delete();

        $this->service->register($this->request, $event->user, $event->remember);
    }
}
