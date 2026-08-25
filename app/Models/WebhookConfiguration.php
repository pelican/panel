<?php

namespace App\Models;

use App\Enums\WebhookScope;
use App\Extensions\Webhooks\WebhookTypeService;
use App\Jobs\ProcessWebhook;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportEvents\HandlesEvents;

/**
 * @property int $id
 * @property string $endpoint
 * @property string $description
 * @property string[] $events
 * @property WebhookScope $scope
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $type
 * @property string|array<array-key, mixed>|null $payload
 * @property array<array-key, mixed>|null $headers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Webhook> $webhooks
 * @property-read int|null $webhooks_count
 *
 * @method static \Database\Factories\WebhookConfigurationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration whereEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration whereEvents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration whereHeaders($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookConfiguration withoutTrashed()
 */
class WebhookConfiguration extends Model
{
    use HandlesEvents, HasFactory, SoftDeletes;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'webhook';

    /** @var string[] */
    protected static array $eventBlacklist = [
        'eloquent.created: App\Models\Webhook',
    ];

    protected $fillable = [
        'name',
        'scope',
        'server_id',
        'type',
        'payload',
        'endpoint',
        'description',
        'events',
        'headers',
    ];

    /**
     * Default values for specific fields in the database.
     */
    protected $attributes = [
        'scope' => WebhookScope::Global,
        'type' => WebhookTypeService::Default,
        'payload' => null,
    ];

    protected function casts(): array
    {
        return [
            'scope' => WebhookScope::class,
            'events' => 'array',
            'payload' => 'array',
            'type' => 'string',
            'headers' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::saved(static function (self $webhookConfiguration): void {
            $changedEvents = collect([
                ...($webhookConfiguration->events),
                ...$webhookConfiguration->getOriginal('events', '[]'),
            ])->unique();

            self::updateCache($changedEvents);
        });

        self::deleted(static function (self $webhookConfiguration): void {
            self::updateCache(collect($webhookConfiguration->events));
        });
    }

    private static function updateCache(Collection $eventList): void
    {
        $eventList->each(function (string $event) {
            cache()->forget("webhooks.$event");
        });

        $eventList->each(function (string $event) {
            cache()->forever("webhooks.$event", static::query()
                ->where('scope', WebhookScope::Global)
                ->whereJsonContains('events', $event)
                ->get());
        });

        cache()->forget('watchedWebhooks');
        cache()->forever('watchedWebhooks', static::where('scope', WebhookScope::Global)
            ->pluck('events')
            ->flatten()
            ->unique()
            ->values()
            ->all());
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return string[] */
    public static function allPossibleEvents(): array
    {
        return collect(static::discoverCustomEvents())
            ->merge(static::allModelEvents())
            ->unique()
            ->filter(fn ($event) => !in_array($event, static::$eventBlacklist))
            ->all();
    }

    /**
     * @param  array<string>  $filterList
     * @return array<string>
     */
    public static function eventList(array $filterList): array
    {
        return collect(static::allPossibleEvents())
            ->filter(function ($event) use ($filterList) {
                foreach ($filterList as $filter) {
                    $eventLower = strtolower($event);
                    $filterLower = strtolower($filter);

                    if ($eventLower === $filterLower) {
                        return true;
                    }

                    $pattern = '/(?:\\\\|\\.)' . preg_quote($filterLower) . '(?:\\\\|:|$)/i';
                    if (preg_match($pattern, $eventLower)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    /** @return array<string> */
    public static function adminEvents(): array
    {
        return static::eventList([
            'User',
            'Role',
            'SSHKey',
            'ApiKey',
            'Token',
            'HasAccessTokens',
            'Node',
            'Allocation',
            'DatabaseHost',
            'Mount',
            'NodeRole',
            'Egg',
            'EggVariable',
            'Plugin',
            'WebhookConfiguration',
            'Webhook',
            'Captcha',
            'Authentication',
            'ActivityLogged',
        ]);
    }

    /** @return array<string> */
    public static function globalServerEvents(): array
    {
        return static::eventList([
            'Server',
            'ServerTransfer',
            'ServerVariable',
            'Allocation',
            'Backup',
            'Database',
            'File',
            'Schedule',
            'Task',
            'Subuser',
            'Installed',
            'SubUserAdded',
            'SubUserRemoved',
        ]);
    }

    /** @return string[] */
    public static function allPossibleServerEvents(): array
    {
        $events = [
            'server:file.read',
            'server:file.write',
            'server:file.rename',
            'server:file.copy',
            'server:file.compress',
            'server:file.decompress',
            'server:file.delete',
            'server:file.create-directory',
            'server:file.uploaded',
            'server:file.pull',
            'server:file.download',

            'server:power.start',
            'server:power.stop',
            'server:power.restart',
            'server:power.kill',

            'server:console.command',

            'server:startup.edit',
            'server:startup.image',
            'server:settings.rename',
            'server:settings.description',
            'server:settings.reinstall',

            'server:allocation.notes',
            'server:allocation.primary',
            'server:allocation.create',
            'server:allocation.delete',

            'server:schedule.create',
            'server:schedule.update',
            'server:schedule.execute',
            'server:schedule.delete',

            'server:task.create',
            'server:task.update',
            'server:task.delete',

            'server:backup.start',
            'server:backup.delete',
            'server:backup.download',
            'server:backup.rename',
            'server:backup.restore',
            'server:backup.restore-complete',
            'server:backup.restore-failed',

            'server:database.create',
            'server:database.rotate-password',
            'server:database.delete',

            'server:subuser.create',
            'server:subuser.update',
            'server:subuser.delete',

            'server:sftp.denied',
        ];

        Event::dispatch('server:webhook.events', [&$events]);

        return array_unique($events);
    }

    /** @return string[] */
    public static function allPossibleAdminOnlyEvents(): array
    {
        $events = static::eventList([
            'User',
            'Role',
            'SSHKey',
            'ApiKey',
            'Token',
            'HasAccessTokens',
            'Node',
            'Allocation',
            'DatabaseHost',
            'Mount',
            'NodeRole',
            'Egg',
            'EggVariable',
            'Plugin',
            'WebhookConfiguration',
            'Webhook',
            'Captcha',
            'Authentication',
            'ActivityLogged',
        ]);

        $serverEvents = collect(static::discoverCustomEvents())
            ->merge(static::allModelEvents())
            ->unique()
            ->filter(fn ($event) => str($event)->contains(Server::class) && !str($event)->contains('Subuser'))
            ->values()
            ->all();

        $events = array_merge($events, $serverEvents);

        Event::dispatch('global:webhook.events', [&$events]);

        return array_values(array_unique($events));
    }

    /** @return array<string, string> */
    public static function filamentCheckboxList(WebhookScope $scope): array
    {
        $list = [];

        if ($scope === WebhookScope::Server) {
            $events = static::allPossibleServerEvents();
            foreach ($events as $event) {
                $list[$event] = static::transformServerEventName($event);
            }
        } else {
            $events = static::allPossibleAdminOnlyEvents();
            foreach ($events as $event) {
                $list[$event] = static::transformClassName($event);
            }
        }

        return $list;
    }

    public static function transformServerEventName(string $event): string
    {
        return str($event)
            ->after('server:')
            ->replace('.', ' → ')
            ->title()
            ->toString();
    }

    public static function transformClassName(string $event): string
    {
        return str($event)
            ->after('eloquent.')
            ->replace('App\\Models\\', '')
            ->replace('App\\Events\\', 'event: ')
            ->toString();
    }

    /** @return string[] */
    public static function allModelEvents(): array
    {
        $eventTypes = ['created', 'updated', 'deleted'];
        $models = static::discoverModels();

        $events = [];
        foreach ($models as $model) {
            foreach ($eventTypes as $eventType) {
                $events[] = "eloquent.$eventType: $model";
            }
        }

        return $events;
    }

    /** @return string[] */
    public static function discoverModels(): array
    {
        // The filesystem scan is memoized per request: this runs on every webhook API
        // write, and allPossibleAdminOnlyEvents() alone needs it twice
        return once(function () {
            $namespace = 'App\\Models\\';
            $directory = app_path('Models');

            $models = [];
            foreach (File::allFiles($directory) as $file) {
                $models[] = $namespace . str($file->getFilename())
                    ->replace([DIRECTORY_SEPARATOR, '.php'], ['\\', '']);
            }

            return $models;
        });
    }

    /** @return string[] */
    public static function discoverCustomEvents(): array
    {
        return once(function () {
            $directory = app_path('Events');

            $events = [];
            foreach (File::allFiles($directory) as $file) {
                $namespace = str($file->getPath())
                    ->after(base_path())
                    ->replace([DIRECTORY_SEPARATOR, '\\app\\'], ['\\', 'App\\']);

                $events[] = $namespace . '\\' . str($file->getFilename())
                    ->replace([DIRECTORY_SEPARATOR, '.php'], ['\\', '']);
            }

            return $events;
        });
    }

    /**
     * @param  array<mixed, mixed>|object  $replacement
     * */
    public function replaceVars(array|object $replacement, string $subject): string
    {
        if (is_object($replacement)) {
            $replacement = $replacement->toArray();
        }

        return preg_replace_callback(
            '/{{(.*?)}}/',
            function ($matches) use ($replacement) {
                $trimmed = trim($matches[1]);

                return data_get($replacement, $trimmed, $trimmed);
            },
            $subject
        ) ?? $subject;
    }

    /** @param array<mixed, mixed> $eventData */
    public function run(?string $eventName = null, ?array $eventData = null): void
    {
        if ($this->scope === WebhookScope::Server) {
            $eventName ??= 'server:file.write';
            $eventData ??= static::getServerWebhookSampleData();
        }

        $eventName ??= 'eloquent.created: '.Server::class;
        $eventData ??= static::getWebhookSampleData();

        ProcessWebhook::dispatch($this, $eventName, [$eventData]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getWebhookSampleData(): array
    {
        return [
            'id' => 4,
            'uuid' => '4864a058-9a3b-44a9-a6cf-c1355e89406e',
            'uuid_short' => '4864a058',
            'node_id' => 1,
            'name' => 'Example Server',
            'owner_id' => 1,
            'memory' => 6144,
            'swap' => 0,
            'disk' => 20480,
            'io' => 500,
            'cpu' => 300,
            'egg_id' => 1,
            'startup' => 'java -Xms128M -XX:MaxRAMPercentage=95.0 -Dterminal.jline=false -Dterminal.ansi=true -jar {{SERVER_JARFILE}}',
            'created_at' => '2025-09-05T01:15:43.000000Z',
            'updated_at' => '2025-09-11T22:45:14.000000Z',
            'allocation_id' => 4,
            'image' => 'ghcr.io/pelican-eggs/yolks:java_21',
            'description' => 'This is an example server description.',
            'skip_scripts' => false,
            'external_id' => null,
            'database_limit' => 5,
            'allocation_limit' => 5,
            'threads' => null,
            'backup_limit' => 5,
            'status' => null,
            'installed_at' => '2025-09-06T03:02:31.000000Z',
            'oom_killer' => false,
            'docker_labels' => [],
            'allocation' => [
                'id' => 4,
                'node_id' => 1,
                'ip' => '0.0.0.0',
                'port' => 25565,
                'server_id' => 4,
                'created_at' => '2025-07-01T20:12:41.000000Z',
                'updated_at' => '2025-09-09T17:47:22.000000Z',
                'ip_alias' => null,
                'notes' => null,
            ],
            'variables' => [
                [
                    'id' => 1,
                    'egg_id' => 1,
                    'name' => 'Build Number',
                    'description' => 'The build number for the paper release.\r\n\r\nLeave at latest to always get the latest version. Invalid versions will default to latest.',
                    'env_variable' => 'BUILD_NUMBER',
                    'default_value' => 'latest',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => ['required', 'string', 'max:20'],
                    'created_at' => '2025-09-05T01:15:43.000000Z',
                    'updated_at' => '2025-09-05T01:15:43.000000Z',
                    'sort' => 4,
                    'server_value' => 'latest',
                ],
                [
                    'id' => 2,
                    'egg_id' => 1,
                    'name' => 'Download Path',
                    'description' => 'A URL to use to download a server.jar rather than the ones in the install script. This is not user\nviewable.',
                    'env_variable' => 'DL_PATH',
                    'default_value' => '',
                    'user_viewable' => false,
                    'user_editable' => false,
                    'rules' => ['nullable', 'string'],
                    'created_at' => '2025-09-05T01:15:43.000000Z',
                    'updated_at' => '2025-09-05T01:15:43.000000Z',
                    'sort' => 3,
                    'server_value' => '',
                ],
                [
                    'id' => 3,
                    'egg_id' => 1,
                    'name' => 'Minecraft Version',
                    'description' => 'The version of minecraft to download. \r\n\r\nLeave at latest to always get the latest version. Invalid versions will default to latest.',
                    'env_variable' => 'MINECRAFT_VERSION',
                    'default_value' => 'latest',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => ['nullable', 'string', 'max:20'],
                    'created_at' => '2025-09-05T01:15:43.000000Z',
                    'updated_at' => '2025-09-05T01:15:43.000000Z',
                    'sort' => 1,
                    'server_value' => '1.21.8',
                ],
                [
                    'id' => 4,
                    'egg_id' => 1,
                    'name' => 'Server Jar File',
                    'description' => 'The name of the server jarfile to run the server with.',
                    'env_variable' => 'SERVER_JARFILE',
                    'default_value' => 'server.jar',
                    'user_viewable' => true,
                    'user_editable' => true,
                    'rules' => ['required', 'regex:/^([\w\d._-]+)(\.jar)$/'],
                    'created_at' => '2025-09-05T01:15:43.000000Z',
                    'updated_at' => '2025-09-05T01:15:43.000000Z',
                    'sort' => 2,
                    'server_value' => 'server.jar',
                ],
            ],
            'server_variables' => [
                'record-21' => [
                    'id' => 21,
                    'server_id' => 4,
                    'variable_id' => 3,
                    'variable_value' => '1.21.8',
                    'created_at' => '2025-09-06T06:00:58.000000Z',
                    'updated_at' => '2025-09-09T17:59:40.000000Z',
                    'variable' => [
                        'id' => 3,
                        'egg_id' => 1,
                        'name' => 'Minecraft Version',
                        'description' => 'The version of minecraft to download. \r\n\r\nLeave at latest to always get the latest version. Invalid versions will default to latest.',
                        'env_variable' => 'MINECRAFT_VERSION',
                        'default_value' => 'latest',
                        'user_viewable' => true,
                        'user_editable' => true,
                        'rules' => ['nullable', 'string', 'max:20'],
                        'created_at' => '2025-09-05T01:15:43.000000Z',
                        'updated_at' => '2025-09-05T01:15:43.000000Z',
                        'sort' => 1,
                    ],
                ],
                'record-22' => [
                    'id' => 22,
                    'server_id' => 4,
                    'variable_id' => 4,
                    'variable_value' => 'server.jar',
                    'created_at' => '2025-09-06T06:00:58.000000Z',
                    'updated_at' => '2025-09-06T06:01:05.000000Z',
                    'variable' => [
                        'id' => 4,
                        'egg_id' => 1,
                        'name' => 'Server Jar File',
                        'description' => 'The name of the server jarfile to run the server with.',
                        'env_variable' => 'SERVER_JARFILE',
                        'default_value' => 'server.jar',
                        'user_viewable' => true,
                        'user_editable' => true,
                        'rules' => ['required', 'regex:/^([\w\d._-]+)(\.jar)$/'],
                        'created_at' => '2025-09-05T01:15:43.000000Z',
                        'updated_at' => '2025-09-05T01:15:43.000000Z',
                        'sort' => 2,
                    ],
                ],
                'record-20' => [
                    'id' => 20,
                    'server_id' => 4,
                    'variable_id' => 2,
                    'variable_value' => '',
                    'created_at' => '2025-09-06T06:00:58.000000Z',
                    'updated_at' => '2025-09-06T06:00:58.000000Z',
                    'variable' => [
                        'id' => 2,
                        'egg_id' => 1,
                        'name' => 'Download Path',
                        'description' => 'A URL to use to download a server.jar rather than the ones in the install script. This is not user\nviewable.',
                        'env_variable' => 'DL_PATH',
                        'default_value' => '',
                        'user_viewable' => false,
                        'user_editable' => false,
                        'rules' => ['nullable', 'string'],
                        'created_at' => '2025-09-05T01:15:43.000000Z',
                        'updated_at' => '2025-09-05T01:15:43.000000Z',
                        'sort' => 3,
                    ],
                ],
                'record-19' => [
                    'id' => 19,
                    'server_id' => 4,
                    'variable_id' => 1,
                    'variable_value' => 'latest',
                    'created_at' => '2025-09-06T06:00:58.000000Z',
                    'updated_at' => '2025-09-06T06:00:58.000000Z',
                    'variable' => [
                        'id' => 1,
                        'egg_id' => 1,
                        'name' => 'Build Number',
                        'description' => 'The build number for the paper release.\r\n\r\nLeave at latest to always get the latest version. Invalid versions will default to latest.',
                        'env_variable' => 'BUILD_NUMBER',
                        'default_value' => 'latest',
                        'user_viewable' => true,
                        'user_editable' => true,
                        'rules' => ['required', 'string', 'max:20'],
                        'created_at' => '2025-09-05T01:15:43.000000Z',
                        'updated_at' => '2025-09-05T01:15:43.000000Z',
                        'sort' => 4,
                    ],
                ],
            ],
            'event' => 'updated: Server',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getServerWebhookSampleData(): array
    {
        return [
            'user' => [
                'uuid' => '12345678-1234-5678-9012-123456789012',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'image' => 'https://www.gravatar.com/avatar/default',
                'admin' => true,
                'language' => 'en',
                'created_at' => '2025-06-01T12:31:50.000000Z',
                'updated_at' => '2025-06-01T12:31:50.000000Z',
            ],
            'server' => [
                'uuid' => '87654321-4321-8765-2109-876543210987',
                'name' => 'Example Server',
                'node' => 'node1.example.com',
                'description' => 'Sample Minecraft server',
            ],
        ];
    }
}
