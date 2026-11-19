<?php

namespace App\Services\Api;

use App\Models\ApiKey;
use App\Services\Helpers\HubCredentials;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

/**
 * Hands a new application API key to Pelican Hub with an OAuth 2.0 authorization code and PKCE
 * (RFC 6749, RFC 7636). The Hub links an admin to the prefilled create-key form, the admin presses
 * Create, the browser goes back to the Hub with a one-time code, and the Hub swaps that code and
 * its PKCE verifier for the key at POST /oauth/token. The key never passes through the browser, and
 * only the Hub session that started the flow holds the verifier, so a link someone else made can't
 * deliver the key to their account.
 */
class HubAuthorizationService
{
    public const CLIENT_ID = 'pelican-hub';

    /** The Hub's connect callback, on the configured hub_url. The only redirect URI accepted. */
    private const CALLBACK_PATH = '/panels/connect/callback';

    private const CODE_TTL_SECONDS = 60;

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * The Hub's callback URL, or null when hub_url isn't an https URL.
     */
    public function redirectUri(): ?string
    {
        $uri = rtrim((string) config('panel.plugin.hub_url'), '/') . self::CALLBACK_PATH;

        return HubCredentials::isHub($uri) ? $uri : null;
    }

    /**
     * The authorization request in a create-key link, when every part of it is valid.
     *
     * @param  array<mixed>  $query
     * @return array{redirect_uri: string, state: string, code_challenge: string}|null
     */
    public function authorizationRequest(array $query): ?array
    {
        $redirectUri = $this->redirectUri();
        $state = $query['state'] ?? null;
        $challenge = $query['code_challenge'] ?? null;

        $valid = $redirectUri !== null
            && ($query['response_type'] ?? null) === 'code'
            && ($query['client_id'] ?? null) === self::CLIENT_ID
            && ($query['redirect_uri'] ?? null) === $redirectUri
            && ($query['code_challenge_method'] ?? null) === 'S256'
            && is_string($state) && $state !== '' && strlen($state) <= 255
            && is_string($challenge) && preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge) === 1;

        if (!$valid) {
            return null;
        }

        return ['redirect_uri' => $redirectUri, 'state' => $state, 'code_challenge' => $challenge];
    }

    /**
     * A one-time code for the key, good for a minute.
     */
    public function issueCode(ApiKey $key, string $codeChallenge): string
    {
        $code = Str::random(64);

        $this->cache->put($this->cacheKey($code), [
            'api_key_id' => $key->id,
            'code_challenge' => $codeChallenge,
        ], self::CODE_TTL_SECONDS);

        return $code;
    }

    /**
     * The key for a code and its PKCE verifier. The code is used up on the first try, right or wrong.
     */
    public function redeem(string $code, string $codeVerifier): ?ApiKey
    {
        $entry = $this->cache->pull($this->cacheKey($code));

        if (!is_array($entry) || preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $codeVerifier) !== 1) {
            return null;
        }

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        if (!hash_equals((string) $entry['code_challenge'], $challenge)) {
            return null;
        }

        return ApiKey::query()
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->find($entry['api_key_id']);
    }

    private function cacheKey(string $code): string
    {
        return 'hub_authorization_code:' . hash('sha256', $code);
    }
}
