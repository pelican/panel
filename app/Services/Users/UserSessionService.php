<?php

namespace App\Services\Users;

use App\Facades\Activity;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Tracks the devices a user is signed in on so they can be listed and signed out individually.
 *
 * Each device gets a random token in a long-lived (encrypted) cookie; only its hash is stored.
 * The row id is also kept in the session, so a revoked session is detected even when the
 * session driver can't destroy sessions remotely (e.g. the cookie driver).
 */
class UserSessionService
{
    public const SESSION_KEY = 'user_session_id';

    /** Same lifetime Laravel uses for the remember-me cookie (400 days). */
    public const COOKIE_MINUTES = 576000;

    /** Don't write last_active_at more often than this. */
    public const TOUCH_INTERVAL_SECONDS = 60;

    public function __construct(private readonly SessionManager $sessions) {}

    public static function cookieName(): string
    {
        return config('session.cookie', 'pelican_session') . '_device';
    }

    /**
     * The device row for the current request, if it is still signed in.
     */
    public function current(Request $request, User $user): ?UserSession
    {
        if ($id = $this->store($request)->get(self::SESSION_KEY)) {
            return $user->sessions()->find($id);
        }

        $token = $request->cookie(self::cookieName());
        if (is_string($token) && $token !== '') {
            return $user->sessions()->where('token_hash', UserSession::hashToken($token))->first();
        }

        return null;
    }

    /**
     * Whether this request's session was tied to a device that has since been signed out.
     */
    public function wasRevoked(Request $request): bool
    {
        return $this->store($request)->has(self::SESSION_KEY);
    }

    public function register(Request $request, User $user, bool $remember): UserSession
    {
        $token = Str::random(64);

        /** @var UserSession $session */
        $session = $user->sessions()->create([
            'session_id' => $this->store($request)->getId(),
            'token_hash' => UserSession::hashToken($token),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'remember' => $remember,
            'last_active_at' => now(),
        ]);

        Cookie::queue(Cookie::make(self::cookieName(), $token, self::COOKIE_MINUTES, httpOnly: true, sameSite: 'lax'));

        $this->store($request)->put(self::SESSION_KEY, $session->id);

        return $session;
    }

    /**
     * Keeps the row in sync with the request: session id (changes on login and regenerate), IP,
     * user agent and last activity (throttled).
     */
    public function touch(Request $request, UserSession $session, bool $remember = false): void
    {
        $session->session_id = $this->store($request)->getId();
        $this->store($request)->put(self::SESSION_KEY, $session->id);

        $session->ip_address = $request->ip();
        $session->user_agent = Str::limit((string) $request->userAgent(), 1000, '');
        $session->remember = $session->remember || $remember;

        if (!$session->last_active_at || $session->last_active_at->diffInSeconds(now(), true) >= self::TOUCH_INTERVAL_SECONDS) {
            $session->last_active_at = now();
        }

        if ($session->isDirty()) {
            $session->save();
        }
    }

    /**
     * Removes the current device on a normal logout.
     */
    public function forgetCurrent(Request $request, User $user): void
    {
        $this->current($request, $user)?->delete();

        $this->store($request)->forget(self::SESSION_KEY);

        Cookie::queue(Cookie::forget(self::cookieName()));
    }

    /**
     * Signs a single device out. Returns false if it is the device making the request.
     */
    public function revoke(User $user, UserSession $session, ?UserSession $current = null): bool
    {
        if ($session->user_id !== $user->id || ($current && $session->is($current))) {
            return false;
        }

        $session->destroyUnderlyingSession();
        $session->delete();

        Activity::event('user:session.revoke')
            ->actor($user)
            ->subject($user)
            ->property('device', $session->displayName())
            ->property('device_ip', $session->ip_address)
            ->log();

        return true;
    }

    /**
     * Signs out every device of the user except $except (usually the current one).
     */
    public function revokeOthers(User $user, ?UserSession $except = null, bool $log = true): int
    {
        $sessions = $user->sessions()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->get();

        foreach ($sessions as $session) {
            $session->destroyUnderlyingSession();
            $session->delete();
        }

        if ($log && $sessions->isNotEmpty()) {
            Activity::event('user:session.revoke-all')
                ->actor($user)
                ->subject($user)
                ->property('count', $sessions->count())
                ->log();
        }

        return $sessions->count();
    }

    /**
     * The request's session, falling back to the app's session store (e.g. Livewire test requests).
     */
    private function store(Request $request): Session
    {
        return $request->hasSession() ? $request->session() : $this->sessions->driver();
    }

    public function rename(User $user, UserSession $session, ?string $name): UserSession
    {
        $name = trim((string) $name);
        $old = $session->displayName();

        $session->name = $name === '' ? null : Str::limit($name, 64, '');
        $session->save();

        Activity::event('user:session.rename')
            ->actor($user)
            ->subject($user)
            ->property('old', $old)
            ->property('new', $session->displayName())
            ->log();

        return $session;
    }
}
