<?php

namespace App\Helpers;

/**
 * Variables set in the real process environment (Docker, systemd, the shell) win over .env,
 * because Dotenv never overwrites them. The snapshot is taken before .env loads, so it tells
 * the two apart; anything saved to .env for one of these keys would never take effect.
 */
final class ProcessEnv
{
    /** Docker's entrypoint copies a few keys out of .env first and lists them here. */
    public const FROM_FILE = 'PANEL_ENV_FROM_FILE';

    /** @var array<string, true> */
    private static array $keys = [];

    /** @param  array<string, mixed>|null  $environment */
    public static function capture(?array $environment = null): void
    {
        $real = $environment === null;
        $environment ??= getenv();
        $fromFile = preg_split('/[\s,]+/', (string) ($environment[self::FROM_FILE] ?? ''), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        self::$keys = array_fill_keys(array_diff(array_keys($environment), $fromFile, [self::FROM_FILE]), true);

        // The entrypoint's copies are from container start; drop them so the current .env wins.
        foreach ($real ? $fromFile : [] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public static function has(string $key): bool
    {
        return isset(self::$keys[$key]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function only(array $values): array
    {
        return array_intersect_key($values, self::$keys);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function except(array $values): array
    {
        return array_diff_key($values, self::$keys);
    }
}
