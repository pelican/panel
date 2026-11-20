<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Users\UserSessionService;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the signed-in devices list up to date, and signs out devices that were revoked
 * or have been inactive for too long.
 */
class TrackUserSession
{
    public function __construct(private readonly UserSessionService $service) {}

    /**
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Filament::auth();
        $user = $guard->user();

        if (!$user instanceof User || !$guard instanceof SessionGuard || !$request->hasSession()) {
            return $next($request);
        }

        $remembered = $request->cookies->has($guard->getRecallerName());
        $session = $this->service->current($request, $user);

        if ($session === null) {
            // The session (or a remember-me login) belonged to a device that was signed out.
            if ($this->service->wasRevoked($request) || $guard->viaRemember()) {
                $this->signOut($request, $guard);
            }

            // A fresh login, or a session that existed before device tracking.
            $this->service->register($request, $user, $remembered);

            return $next($request);
        }

        if ($session->isInactive()) {
            $session->destroyUnderlyingSession();
            $session->delete();

            $this->signOut($request, $guard);
        }

        $this->service->touch($request, $session, $guard->viaRemember());

        return $next($request);
    }

    /**
     * @throws AuthenticationException
     */
    private function signOut(Request $request, SessionGuard $guard): never
    {
        $guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        throw new AuthenticationException(redirectTo: Filament::getLoginUrl());
    }
}
