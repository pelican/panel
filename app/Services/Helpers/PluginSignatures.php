<?php

namespace App\Services\Helpers;

use RuntimeException;

/**
 * Ed25519 signatures on plugin archives (pelican/wings plugin contract v1, R2.5), the same rules as Wings:
 *
 *  - a signature that is present must verify against one of the trusted keys, whatever the policy,
 *    because one that doesn't means the archive or its listing was tampered with;
 *  - no signature is fine unless PANEL_PLUGIN_REQUIRE_SIGNATURES is on.
 *
 * The signature covers the 32 raw bytes of the archive's SHA-256 digest. Trusted keys are base64 Ed25519
 * public keys in PANEL_PLUGIN_TRUSTED_KEYS, separated by commas or whitespace, each optionally `id:key`.
 */
class PluginSignatures
{
    /**
     * @param  string  $digest  the archive's raw (binary) SHA-256 digest
     *
     * @throws RuntimeException
     */
    public function enforce(string $digest, ?string $signature): void
    {
        if (blank($signature)) {
            if ($this->required()) {
                throw new RuntimeException(trans('admin/plugin.registry.signature_required'));
            }

            return;
        }

        $this->verify($digest, $signature);
    }

    /** @throws RuntimeException */
    public function verify(string $digest, string $signature): void
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException(trans('admin/plugin.registry.signature_unsupported'));
        }

        $raw = base64_decode(trim($signature), true);

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($digest) !== 32) {
            throw new RuntimeException(trans('admin/plugin.registry.signature_malformed'));
        }

        foreach ($this->trustedKeys() as $key) {
            if (sodium_crypto_sign_verify_detached($raw, $digest, $key)) {
                return;
            }
        }

        throw new RuntimeException(trans('admin/plugin.registry.signature_untrusted'));
    }

    public function required(): bool
    {
        return (bool) config('panel.plugin.require_signatures', false);
    }

    /**
     * The usable trusted keys as raw 32-byte public keys. Malformed ones are skipped, as in Wings.
     *
     * @return string[]
     */
    public function trustedKeys(): array
    {
        $keys = [];

        foreach (preg_split('/[\s,]+/', (string) config('panel.plugin.trusted_keys'), flags: PREG_SPLIT_NO_EMPTY) ?: [] as $key) {
            // `id:key`: base64 never contains ':', so everything after the first one is the key.
            if (str_contains($key, ':')) {
                $key = substr($key, strpos($key, ':') + 1);
            }

            $raw = base64_decode($key, true);

            if ($raw !== false && strlen($raw) === 32) {
                $keys[] = $raw;
            }
        }

        return $keys;
    }
}
