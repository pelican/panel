<?php

namespace App\Models;

use App\Contracts\Validatable;
use App\Enums\ContainerStatus;
use App\Enums\ServerResourceType;
use App\Enums\ServerState;
use App\Enums\WebhookScope;
use App\Exceptions\Http\Server\ServerStateConflictException;
use App\Models\Traits\HasIcon;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Subusers\SubuserDeletionService;
use App\Traits\HasValidation;
use Carbon\CarbonInterface;
use Exception;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

/**
 * \App\Models\Server.
 *
 * @property int $id
 * @property string $uuid
 * @property string $uuid_short
 * @property int $node_id
 * @property string $name
 * @property int $owner_id
 * @property int $memory
 * @property int $swap
 * @property int $disk
 * @property int $io
 * @property int $cpu
 * @property int $egg_id
 * @property string $startup
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $allocation_id
 * @property string $image
 * @property string|null $description
 * @property bool $skip_scripts
 * @property string|null $external_id
 * @property int|null $database_limit
 * @property int|null $allocation_limit
 * @property string|null $threads
 * @property int $backup_limit
 * @property ServerState|null $status
 * @property Carbon|null $installed_at
 * @property bool $oom_killer
 * @property array<array-key, mixed>|null $docker_labels
 * @property-read Collection<int, ActivityLog> $activity
 * @property-read int|null $activity_count
 * @property-read Allocation|null $allocation
 * @property-read Collection<int, Allocation> $allocations
 * @property-read int|null $allocations_count
 * @property-read Collection<int, Backup> $backups
 * @property-read int|null $backups_count
 * @property-read ServerState|ContainerStatus $condition
 * @property-read Collection<int, Database> $databases
 * @property-read int|null $databases_count
 * @property-read Egg $egg
 * @property-read Collection<int, EggVariable> $eggVariables
 * @property-read int|null $egg_variables_count
 * @property-read string|null $icon
 * @property-read Collection<int, Mount> $mounts
 * @property-read int|null $mounts_count
 * @property-read Node $node
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Schedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read Collection<int, ServerVariable> $serverVariables
 * @property-read int|null $server_variables_count
 * @property-read Collection<int, Subuser> $subusers
 * @property-read int|null $subusers_count
 * @property-read Collection<int, ServerUserSettings> $userSettings
 * @property-read int|null $user_settings_count
 * @property-read ServerTransfer|null $transfer
 * @property-read User $user
 * @property-read Collection<int, EggVariable> $variables
 * @property-read int|null $variables_count
 *
 * @method static \Database\Factories\ServerFactory factory($count = null, $state = [])
 * @method static Builder<static>|Server newModelQuery()
 * @method static Builder<static>|Server newQuery()
 * @method static Builder<static>|Server query()
 * @method static Builder<static>|Server whereAllocationId($value)
 * @method static Builder<static>|Server whereAllocationLimit($value)
 * @method static Builder<static>|Server whereBackupLimit($value)
 * @method static Builder<static>|Server whereCpu($value)
 * @method static Builder<static>|Server whereCreatedAt($value)
 * @method static Builder<static>|Server whereDatabaseLimit($value)
 * @method static Builder<static>|Server whereDescription($value)
 * @method static Builder<static>|Server whereDisk($value)
 * @method static Builder<static>|Server whereDockerLabels($value)
 * @method static Builder<static>|Server whereEggId($value)
 * @method static Builder<static>|Server whereExternalId($value)
 * @method static Builder<static>|Server whereId($value)
 * @method static Builder<static>|Server whereImage($value)
 * @method static Builder<static>|Server whereInstalledAt($value)
 * @method static Builder<static>|Server whereIo($value)
 * @method static Builder<static>|Server whereMemory($value)
 * @method static Builder<static>|Server whereName($value)
 * @method static Builder<static>|Server whereNodeId($value)
 * @method static Builder<static>|Server whereOomKiller($value)
 * @method static Builder<static>|Server whereOwnerId($value)
 * @method static Builder<static>|Server whereSkipScripts($value)
 * @method static Builder<static>|Server whereStartup($value)
 * @method static Builder<static>|Server whereStatus($value)
 * @method static Builder<static>|Server whereSwap($value)
 * @method static Builder<static>|Server whereThreads($value)
 * @method static Builder<static>|Server whereUpdatedAt($value)
 * @method static Builder<static>|Server whereUuid($value)
 * @method static Builder<static>|Server whereUuidShort($value)
 */
class Server extends Model implements HasAvatar, Validatable
{
    use HasFactory;
    use HasIcon;
    use HasValidation;
    use Notifiable;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal. Also used as name for api key permissions.
     */
    public const RESOURCE_NAME = 'server';

    /**
     * Default values when creating the model. We want to switch to disabling OOM killer
     * on server instances unless the user specifies otherwise in the request.
     */
    protected $attributes = [
        'status' => ServerState::Installing,
        'oom_killer' => false,
        'installed_at' => null,
    ];

    /**
     * The default relationships to load for all server models.
     */
    protected $with = ['allocation'];

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT, 'installed_at'];

    /** @var array<array-key, string[]> */
    public static array $validationRules = [
        'external_id' => ['sometimes', 'nullable', 'string', 'between:1,255', 'unique:servers'],
        'owner_id' => ['required', 'integer', 'exists:users,id'],
        'name' => ['required', 'string', 'min:1', 'max:255'],
        'node_id' => ['required', 'exists:nodes,id'],
        'description' => ['string'],
        'status' => ['nullable', 'string'],
        'memory' => ['required', 'numeric', 'min:0'],
        'swap' => ['required', 'numeric', 'min:-1'],
        'io' => ['required', 'numeric', 'between:0,1000'],
        'cpu' => ['required', 'numeric', 'min:0'],
        'threads' => ['nullable', 'regex:/^[0-9-,]+$/'],
        'oom_killer' => ['sometimes', 'boolean'],
        'disk' => ['required', 'numeric', 'min:0'],
        'allocation_id' => ['sometimes', 'nullable', 'unique:servers', 'exists:allocations,id'],
        'egg_id' => ['required', 'exists:eggs,id'],
        'startup' => ['required', 'string'],
        'skip_scripts' => ['sometimes', 'boolean'],
        'image' => ['required', 'string', 'max:255'],
        'database_limit' => ['present', 'nullable', 'integer', 'min:0'],
        'allocation_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
        'backup_limit' => ['present', 'nullable', 'integer', 'min:0'],
    ];

    protected function casts(): array
    {
        return [
            'node_id' => 'integer',
            'status' => ServerState::class,
            'skip_scripts' => 'boolean',
            'owner_id' => 'integer',
            'memory' => 'integer',
            'swap' => 'integer',
            'disk' => 'integer',
            'io' => 'integer',
            'cpu' => 'integer',
            'oom_killer' => 'boolean',
            'allocation_id' => 'integer',
            'egg_id' => 'integer',
            'database_limit' => 'integer',
            'allocation_limit' => 'integer',
            'backup_limit' => 'integer',
            self::CREATED_AT => 'datetime',
            self::UPDATED_AT => 'datetime',
            'installed_at' => 'datetime',
            'docker_labels' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $server) {
            $subuser = $server->subusers()->where('user_id', $server->owner_id)->first();
            if ($subuser) {
                // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
                app(SubuserDeletionService::class)->handle($subuser, $server);
            }
        });
    }

    /**
     * Returns the format for server allocations when communicating with the Daemon.
     *
     * @return array<string, array<int>>
     */
    public function getAllocationMappings(): array
    {
        if (!$this->allocation) {
            return ['' => []];
        }

        return $this->allocations->where('node_id', $this->node_id)->groupBy('ip')->map(function ($item) {
            return $item->pluck('port');
        })->toArray();
    }

    public function isInstalled(): bool
    {
        return $this->status !== ServerState::Installing && !$this->isFailedInstall();
    }

    public function isFailedInstall(): bool
    {
        return $this->status === ServerState::InstallFailed || $this->status === ServerState::ReinstallFailed;
    }

    public function isSuspended(): bool
    {
        return $this->status === ServerState::Suspended;
    }

    /**
     * Gets the user who owns the server.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Gets the subusers associated with a server.
     *
     * @return HasMany<Subuser, $this>
     */
    public function subusers(): HasMany
    {
        return $this->hasMany(Subuser::class, 'server_id', 'id');
    }

    /**
     * Gets the per-user settings associated with a server.
     *
     * @return HasMany<ServerUserSettings, $this>
     */
    public function userSettings(): HasMany
    {
        return $this->hasMany(ServerUserSettings::class);
    }

    /**
     * Gets the default allocation for a server.
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    /**
     * Gets all allocations associated with this server.
     *
     * @return HasMany<Allocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    /**
     * Gets information for the egg associated with this server.
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    public function eggVariables(): HasMany
    {
        return $this->hasMany(EggVariable::class, 'egg_id', 'egg_id');
    }

    /**
     * Gets information for the egg variables associated with this server.
     *
     * @return HasMany<EggVariable, $this>
     */
    public function variables(): HasMany
    {
        return $this->hasMany(EggVariable::class, 'egg_id', 'egg_id')
            ->select(['egg_variables.*', 'server_variables.variable_value as server_value'])
            ->leftJoin('server_variables', function (JoinClause $join) {
                // Don't forget to join against the server ID as well since the way we're using this relationship
                // would actually return all the variables and their values for _all_ servers using that egg,
                // rather than only the server for this model.
                $join->on('server_variables.variable_id', 'egg_variables.id')
                    ->where('server_variables.server_id', $this->id);
            });
    }

    public function serverVariables(): HasMany
    {
        return $this->hasMany(ServerVariable::class);
    }

    public function ensureVariablesExist(): void
    {
        foreach ($this->eggVariables as $variable) {
            ServerVariable::firstOrCreate([
                'server_id' => $this->id,
                'variable_id' => $variable->id,
            ], [
                'variable_value' => $variable->default_value,
            ]);
        }
    }

    /**
     * Gets information for the node associated with this server.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Gets information for the tasks associated with this server.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Gets all databases associated with a server.
     */
    public function databases(): HasMany
    {
        return $this->hasMany(Database::class);
    }

    /**
     * Returns the associated server transfer.
     */
    public function transfer(): HasOne
    {
        return $this->hasOne(ServerTransfer::class)->whereNull('successful')->orderByDesc('id');
    }

    /**
     * @return HasMany<Backup, $this>
     */
    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function mounts(): MorphToMany
    {
        return $this->morphToMany(Mount::class, 'mountable');
    }

    /**
     * @return HasMany<WebhookConfiguration, $this>
     */
    public function webhookConfigurations(): HasMany
    {
        return $this->hasMany(WebhookConfiguration::class, 'server_id', 'id')
            ->where('scope', WebhookScope::Server);
    }

    /**
     * Returns all the activity log entries where the server is the subject.
     */
    public function activity(): MorphToMany
    {
        return $this->morphToMany(ActivityLog::class, 'subject', 'activity_log_subjects');
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return match ($field) {
            'uuid', 'uuid_short' => $this->where('uuid_short', $value)->orWhere('uuid', $value)->firstOrFail(),
            default => $this->where('id', $value)->firstOrFail(),
        };
    }

    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return match ($childType) {
            'user' => $this->subusers()->whereRelation('user', 'uuid', $value)->firstOrFail()->user,
            default => parent::resolveChildRouteBinding($childType, $value, $field),
        };
    }

    public function isInConflictState(): bool
    {
        return $this->isSuspended() || $this->node->isUnderMaintenance() || !$this->isInstalled() || $this->status === ServerState::RestoringBackup || !is_null($this->transfer);
    }

    /**
     * Checks if the server is currently in a user-accessible state. If not, an
     * exception is raised. This should be called whenever something needs to make
     * sure the server is not in a weird state that should block user access.
     *
     * @throws ServerStateConflictException
     */
    public function validateCurrentState(): void
    {
        throw_if($this->isInConflictState(), new ServerStateConflictException($this));
    }

    /**
     * Checks if the server is currently in a transferable state. If not, an
     * exception is raised. This should be called whenever something needs to make
     * sure the server can be transferred and is not currently being transferred
     * or installed.
     */
    public function validateTransferState(): void
    {
        if (!$this->isInstalled() ||
            $this->status === ServerState::RestoringBackup ||
            !is_null($this->transfer)) {
            throw new ServerStateConflictException($this);
        }
    }

    /**
     * Sends a command or multiple commands to a running server instance.
     *
     * @param  string[]|string  $command
     *
     * @throws ConnectionException
     */
    public function send(array|string $command): ResponseInterface
    {
        return Http::daemon($this->node)->post("/api/servers/{$this->uuid}/commands", [
            'commands' => is_array($command) ? $command : [$command],
        ])->toPsrResponse();
    }

    public function retrieveStatus(): ContainerStatus
    {
        if ($this->node->isUnderMaintenance()) {
            return ContainerStatus::Missing;
        }

        return cache()->remember("servers.$this->uuid.status", now()->addSeconds(15), function () {
            // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
            $details = app(DaemonServerRepository::class)->setServer($this)->getDetails();

            return ContainerStatus::tryFrom(Arr::get($details, 'state')) ?? ContainerStatus::Missing;
        });
    }

    /**
     * @return array<mixed>
     */
    public function retrieveResources(): array
    {
        if ($this->node->isUnderMaintenance()) {
            return [];
        }

        return cache()->remember("servers.$this->uuid.resources", now()->addSeconds(15), function () {
            // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
            $details = app(DaemonServerRepository::class)->setServer($this)->getDetails();

            return Arr::get($details, 'utilization', []);
        });
    }

    public function formatResource(ServerResourceType $resourceType, int $precision = 2): string
    {
        $resourceAmount = $resourceType->getResourceAmount($this);

        if ($resourceType->isTime()) {
            if (!is_null($this->status)) {
                return $this->status->getLabel();
            }

            if ($resourceAmount === 0) {
                return ContainerStatus::Offline->getLabel();
            }

            return now()->subMillis($resourceAmount)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE, short: true, parts: 4);
        }

        if ($resourceAmount === 0 & $resourceType->isLimit()) {
            // Unlimited symbol
            return "\u{221E}";
        }

        if ($resourceType->isPercentage()) {
            return format_number($resourceAmount, precision: $precision) . '%';
        }

        return convert_bytes_to_readable($resourceAmount, decimals: $precision, base: 3);
    }

    public function condition(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status ?? $this->retrieveStatus(),
        );
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->icon ?? $this->egg->icon;
    }

    public function getSftpUrl(?string $username = null, ?string $directory = null): string
    {
        $username ??= user()?->username;

        if (!is_null($directory)) {
            $directory = explode('/', trim($directory, '/'));
            $directory = array_map(fn (string $part) => rawurlencode($part), $directory);
            $directory = '/' . implode('/', $directory);
        }

        if ($directory === '/') {
            $directory = null;
        }

        $fqdn = $this->node->daemon_sftp_alias ?? $this->node->fqdn;

        return 'sftp://' . rawurlencode($username) . '.' . $this->uuid_short . '@' . $fqdn . ':' . $this->node->daemon_sftp . $directory;
    }
}
