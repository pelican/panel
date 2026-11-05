<?php

namespace App\Settings;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;

/**
 * Keyed settings for things that can't be static typed classes: OAuth and captcha providers
 * (plugins can add their own) and plugin settings. Rows share spatie's settings table.
 */
class KeyValueStore
{
    public const SECRET_PATTERNS = ['*SECRET*', '*PASSWORD*', '*TOKEN*', '*_KEY'];

    /** @var array<string, array<string, mixed>> */
    private array $groups = [];

    public function __construct(private SettingsRepository $repository) {}

    public function get(string $key, mixed $default = null, string $group = 'extensions'): mixed
    {
        return $this->all($group)[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(string $group = 'extensions'): array
    {
        // ponytail: memoized per request, one query per group; add a cache if it shows up in profiling
        return $this->groups[$group] ??= rescue(fn () => collect($this->repository->getPropertiesInGroup($group))
            // A secret that won't decrypt (e.g. APP_KEY changed) is reported and dropped on its own,
            // so it can't take the rest of the group (like CAPTCHA_*_ENABLED) down with it.
            ->map(fn ($value, $key) => self::isSecret($key) && is_string($value) && filled($value) ? rescue(fn () => Crypt::decryptString($value)) : $value)
            ->all(), [], false);
    }

    /** @param array<string, mixed> $values */
    public function set(array $values, string $group = 'extensions'): void
    {
        $payload = collect($values)
            ->map(fn ($value) => self::normalize($value))
            ->map(fn ($value, $key) => self::isSecret($key) && filled($value) ? Crypt::encryptString((string) $value) : $value)
            ->all();

        $this->repository->updatePropertiesPayload($group, $payload);
        unset($this->groups[$group]);
    }

    public static function isSecret(string $key): bool
    {
        return Str::is(self::SECRET_PATTERNS, Str::upper($key));
    }

    /** Same coercion env() applies, since these values used to come from .env. */
    public static function normalize(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
