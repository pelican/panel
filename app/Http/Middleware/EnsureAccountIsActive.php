<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureAccountIsActive
{
    public const SESSION_KEY = 'auth_remember_token_hash';

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
            $sessionTokenHash = $request->session()->get(self::SESSION_KEY);
            $currentTokenHash = hash('sha256', (string) $user->getRememberToken());

            if (!is_string($sessionTokenHash) || !hash_equals($sessionTokenHash, $currentTokenHash)) {
                return $this->logout($request, trans('auth.session_expired'));
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
