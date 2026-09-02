<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureAccountIsActive
{
    public const SESSION_KEY = 'auth_session_version';

    public function handle(Request $request, Closure $next): mixed
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        if ($user->isSuspended()) {
            if ($request->is('api/*') || $request->expectsJson()) {
                throw new AccessDeniedHttpException(User::suspensionMessage());
            }

            return $this->logout($request, User::suspensionMessage());
        }

        if ($request->hasSession()) {
            $sessionVersion = $request->session()->get(self::SESSION_KEY);
            if (is_null($sessionVersion)) {
                $request->session()->put(self::SESSION_KEY, $user->auth_session_version);
            } elseif ((int) $sessionVersion !== $user->auth_session_version) {
                return $this->logout($request, 'Your session has expired. Please sign in again.');
            }
        }

        return $next($request);
    }

    private function logout(Request $request, string $message): mixed
    {
        auth()->guard()->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Notification::make()->title($message)->danger()->persistent()->send();

        return redirect()->route('filament.app.auth.login');
    }
}
