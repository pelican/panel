<?php

namespace App\Traits\Filament;

use App\Facades\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes admin-panel audit events ({key}:create|update|delete) through the
 * activity log. The admin panel is not tenant-scoped, so the subject is always
 * attached explicitly; the actor auto-resolves to the logged-in admin.
 */
trait LogsAdminActivity
{
    /**
     * Model basenames whose activity key differs from their camel-cased name.
     * Keys align with the permission model names in RolePermissionModels.
     *
     * @var array<string, string>
     */
    protected static array $activityKeyOverrides = [
        'WebhookConfiguration' => 'webhook',
    ];

    /**
     * Attribute name patterns that are never written to the log in clear text.
     *
     * @var string[]
     */
    protected static array $redactedAttributePatterns = [
        'password',
        '*password*',
        '*_secret',
        '*secret*',
        '*token*',
        'api_key',
        '*_key',
    ];

    /**
     * Attributes used to identify a record in create/delete events.
     *
     * @var string[]
     */
    protected static array $identifyingAttributes = ['id', 'uuid', 'name', 'username', 'email', 'identifier', 'endpoint', 'database', 'ip', 'port'];

    public static function activityKey(Model $record): string
    {
        $basename = class_basename($record);

        return static::$activityKeyOverrides[$basename] ?? Str::camel($basename);
    }

    /** @param array<string, mixed> $properties */
    public static function logAdminActivity(string $action, Model $record, array $properties = []): void
    {
        Activity::event(static::activityKey($record) . ':' . $action)
            ->subject($record)
            ->property(array_merge(static::identify($record), $properties))
            ->log();
    }

    /**
     * Identifying attributes only; the subject link carries the rest.
     *
     * @return array<string, mixed>
     */
    public static function identify(Model $record): array
    {
        $attributes = array_intersect_key($record->getAttributes(), array_flip(static::$identifyingAttributes));

        return collect($attributes)->map(fn ($value, $key) => static::redact($key, $value))->all();
    }

    /**
     * Old-to-new pairs for every changed attribute, secrets masked.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function buildDiff(array $old, array $new): array
    {
        $diff = [];

        foreach ($new as $key => $value) {
            if ($key === 'updated_at' || ($old[$key] ?? null) === $value) {
                continue;
            }

            $diff[$key] = [
                'old' => static::redact($key, $old[$key] ?? null),
                'new' => static::redact($key, $value),
            ];
        }

        return $diff;
    }

    public static function redact(string $key, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // URLs can carry credentials in user-info or the query string.
        if (strtolower($key) === 'endpoint' && is_string($value)) {
            $parts = parse_url($value);

            if (!is_array($parts) || !isset($parts['host'])) {
                return '********';
            }

            return sprintf(
                '%s%s%s%s',
                isset($parts['scheme']) ? $parts['scheme'] . '://' : '',
                $parts['host'],
                isset($parts['port']) ? ':' . $parts['port'] : '',
                $parts['path'] ?? '',
            );
        }

        if (Str::is(static::$redactedAttributePatterns, strtolower($key))) {
            return '********';
        }

        return $value;
    }
}
