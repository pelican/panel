<?php

namespace App\Http\Middleware;

use App\Helpers\PasskeyOrigin;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkeys;
use Symfony\Component\HttpFoundation\Response;

class AllowPasskeyOrigin
{
    /**
     * Hosts browsers treat as a secure context without TLS.
     */
    private const LoopbackHosts = ['localhost', '127.0.0.1', '[::1]'];

    /**
     * Passkeys are bound to a relying party ID (a domain), and the browser refuses to run
     * a ceremony unless the page it runs on belongs to that domain. The server then checks
     * the origin a second time against a list built from APP_URL, which rejects requests
     * the browser considered perfectly valid whenever the two disagree on scheme or port -
     * a panel reached on a non-standard port, for example.
     *
     * Accept the request's own origin when its host is exactly the relying party ID. The
     * browser has already guaranteed the domain matches, so this gives nothing away, while
     * an origin on any other domain stays rejected.
     *
     * Mutating the config is the only seam the package offers: Passkeys::allowedOrigins()
     * reads it on every ceremony, and there is no callback or container binding to swap.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('passkeys.strict_origin')) {
            return $next($request);
        }

        $origin = PasskeyOrigin::normalize($request->headers->get('Origin') ?? $request->getSchemeAndHttpHost());

        if ($origin !== null && $this->belongsToRelyingParty($origin, $request)) {
            config(['passkeys.allowed_origins' => PasskeyOrigin::all([
                ...config('passkeys.allowed_origins'),
                $origin,
            ])]);
        }

        return $next($request);
    }

    /**
     * Determine whether an origin is one a browser could have produced for our relying
     * party: the domain has to be the relying party ID itself, and the page has to have
     * been a secure context, since that is the only place the WebAuthn API exists.
     *
     * The Origin header is chosen by the client, so it is checked against the host the
     * request was routed to, leaving the port as the only part a caller can influence.
     * The server's scheme can't be used the same way: without TRUSTED_PROXIES set, a
     * panel behind a TLS terminating proxy reports plain http and would fail the check
     * below.
     */
    private function belongsToRelyingParty(string $origin, Request $request): bool
    {
        $parts = parse_url($origin);
        $host = $parts['host'] ?? null;
        $scheme = $parts['scheme'] ?? null;

        if ($host !== Passkeys::relyingPartyId() || $host !== $request->getHost()) {
            return false;
        }

        return $scheme === 'https' || ($scheme === 'http' && in_array($host, self::LoopbackHosts, true));
    }
}
