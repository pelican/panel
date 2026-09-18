<?php

namespace App\Observers;

use App\Services\Activity\ActivityLogService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes {key}:create|update|delete audit events for the models it observes.
 *
 * Customer-facing surfaces (server panel, app panel, client API) already log
 * their own richer events, so they are skipped. Everything else is logged:
 * admin panel, application API, console and daemon writes.
 */
class AuditObserver
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

    /**
     * Attribute changes that are never worth an audit row.
     *
     * @var string[]
     */
    protected static array $ignoredAttributes = ['updated_at', 'remember_token', 'last_used_at'];

    // Injected rather than the Activity facade: the facade caches one service
    // instance, so an observer firing inside an outer Activity::transaction()
    // would clobber the pending entry.
    public function __construct(protected ActivityLogService $activity) {}

    public function created(Model $model): void
    {
        $this->log('create', $model);
    }

    public function updated(Model $model): void
    {
        // Fires before syncOriginal(), so raw original vs raw changes is apples to apples.
        $changes = static::buildDiff($model->getRawOriginal(), $model->getChanges());

        if ($changes === []) {
            return;
        }

        $this->log('update', $model, ['changes' => $changes]);
    }

    public function deleted(Model $model): void
    {
        $this->log('delete', $model);
    }

    /** @param array<string, mixed> $properties */
    protected function log(string $action, Model $model, array $properties = []): void
    {
        $panel = Filament::getCurrentPanel()?->getId();

        if (($panel !== null && $panel !== 'admin') || request()->is('api/client/*')) {
            return;
        }

        $this->activity
            ->event(static::activityKey($model) . ':' . $action)
            ->subject($model)
            ->property(array_merge(static::identify($model), $properties))
            ->log();
    }

    public static function activityKey(Model $record): string
    {
        $basename = class_basename($record);

        return static::$activityKeyOverrides[$basename] ?? Str::camel($basename);
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
            if (in_array($key, static::$ignoredAttributes, true) || ($old[$key] ?? null) === $value) {
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
