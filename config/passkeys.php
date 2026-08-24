<?php

use App\Helpers\PasskeyOrigin;
use App\Http\Middleware\AddPasskeyErrorMessage;
use App\Http\Middleware\AllowPasskeyOrigin;

$appOrigin = PasskeyOrigin::normalize(config('app.url')) ?? 'http://localhost';

return [
    /*
     * Passkeys are bound to this domain. It has to match the host users actually browse
     * the panel at, otherwise the browser refuses to run the ceremony at all. Defaults to
     * the host of APP_URL; set PASSKEYS_RELYING_PARTY_ID to override it with either a bare
     * domain or a full URL, both of which are reduced to the domain.
     */
    'relying_party_id' => (string) parse_url(
        PasskeyOrigin::normalize((string) env('PASSKEYS_RELYING_PARTY_ID')) ?? $appOrigin,
        PHP_URL_HOST,
    ),

    /*
     * Only ceremonies completed on one of these origins are accepted. Any origin on the
     * relying party domain itself is added per request by AllowPasskeyOrigin, so this only
     * needs entries for hosts that differ from it - subdomains, when the relying party ID
     * is a parent domain shared by several panels. List those in PASSKEYS_ALLOWED_ORIGINS
     * as a comma separated list; browsers reject anything outside the relying party domain
     * before it ever reaches us.
     */
    'allowed_origins' => PasskeyOrigin::all(array_merge(
        [$appOrigin],
        explode(',', (string) env('PASSKEYS_ALLOWED_ORIGINS')),
    )),

    /*
     * Accepting any origin on the relying party domain means the port stops being part of
     * the check, so a panel on a non-standard port works without extra configuration. Set
     * PASSKEYS_STRICT_ORIGIN=true to turn that off and accept only the origins listed
     * above, exactly as written, port included.
     */
    'strict_origin' => env('PASSKEYS_STRICT_ORIGIN', false),

    'middleware' => ['web', AddPasskeyErrorMessage::class, AllowPasskeyOrigin::class],
    'management_middleware' => ['auth'],
];
