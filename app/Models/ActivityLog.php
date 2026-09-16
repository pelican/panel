<?php

namespace App\Models;

use App\Enums\TablerIcon;
use App\Traits\HasValidation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;

/**
 * \App\Models\ActivityLog.
 *
 * @property int $id
 * @property string $event
 * @property string $ip
 * @property string|null $description
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property Collection<array-key, mixed> $properties
 * @property Carbon $timestamp
 * @property int|null $api_key_id
 * @property-read Model|\Eloquent|null $actor
 * @property-read ApiKey|null $apiKey
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ActivityLogSubject> $subjects
 * @property-read int|null $subjects_count
 *
 * @method static Builder<static>|ActivityLog forActor(\Illuminate\Database\Eloquent\Model $actor)
 * @method static Builder<static>|ActivityLog forEvent(string $action)
 * @method static Builder<static>|ActivityLog hideAdminActivity(\App\Models\Server $server)
 * @method static Builder<static>|ActivityLog newModelQuery()
 * @method static Builder<static>|ActivityLog newQuery()
 * @method static Builder<static>|ActivityLog query()
 * @method static Builder<static>|ActivityLog whereActorId($value)
 * @method static Builder<static>|ActivityLog whereActorType($value)
 * @method static Builder<static>|ActivityLog whereApiKeyId($value)
 * @method static Builder<static>|ActivityLog whereDescription($value)
 * @method static Builder<static>|ActivityLog whereEvent($value)
 * @method static Builder<static>|ActivityLog whereId($value)
 * @method static Builder<static>|ActivityLog whereIp($value)
 * @method static Builder<static>|ActivityLog whereProperties($value)
 * @method static Builder<static>|ActivityLog whereTimestamp($value)
 */
class ActivityLog extends Model implements HasIcon, HasLabel
{
    use HasValidation;
    use MassPrunable;

    public const RESOURCE_NAME = 'activity_log';

    /**
     * Tracks all the events we no longer wish to display to users. These are either legacy
     * events or just events where we never ended up using the associated data.
     */
    public const DISABLED_EVENTS = ['server:file.upload'];

    public $timestamps = false;

    protected $guarded = [
        'id',
        'timestamp',
    ];

    protected $with = ['subjects'];

    /** @var array<array-key, string[]> */
    public static array $validationRules = [
        'event' => ['required', 'string'],
        'ip' => ['required', 'string'],
        'description' => ['nullable', 'string'],
        'properties' => ['array'],
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'collection',
            'timestamp' => 'datetime',
        ];
    }

    public function actor(): MorphTo
    {
        return $this->morphTo()->withTrashed()->withoutGlobalScopes();
    }

    /**
     * @return HasMany<ActivityLogSubject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(ActivityLogSubject::class);
    }

    public function apiKey(): HasOne
    {
        return $this->hasOne(ApiKey::class, 'id', 'api_key_id');
    }

    public function scopeForEvent(Builder $builder, string $action): Builder
    {
        return $builder->where('event', $action);
    }

    /**
     * Scopes a query to only return results where the actor is a given model.
     */
    public function scopeForActor(Builder $builder, Model $actor): Builder
    {
        return $builder->whereMorphedTo('actor', $actor);
    }

    /**
     * Hides entries whose actor holds any role (an admin) but is not the owner
     * or a subuser of the given server.
     */
    public function scopeHideAdminActivity(Builder $builder, Server $server): Builder
    {
        $members = $server->subusers()->pluck('user_id')->merge([$server->owner_id]);

        return $builder->select('activity_logs.*')
            ->leftJoin('users', function (JoinClause $join) {
                $join->on('users.id', 'activity_logs.actor_id')
                    ->where('activity_logs.actor_type', (new User())->getMorphClass());
            })
            ->where(function (Builder $builder) use ($members) {
                $builder->whereNull('users.id')
                    ->orWhereNotIn('users.id', User::whereHas('roles')->select('id'))
                    ->orWhereIn('users.id', $members);
            });
    }

    public function prunable(): Builder
    {
        throw_if(is_null(config('activity.prune_days')), new LogicException('Cannot prune activity logs: no "prune_days" configuration value is set.'));

        return static::where('timestamp', '<=', Carbon::now()->subDays(config('activity.prune_days')));
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->timestamp = Carbon::now();
        });

        // Activity logs are append-only. Pruning still works because MassPrunable
        // deletes through the query builder and never fires these model events;
        // switching to the non-mass Prunable trait would break it.
        static::updating(fn () => throw new LogicException('Activity logs are append-only and cannot be updated.'));
        static::deleting(fn () => throw new LogicException('Activity logs are append-only and cannot be deleted.'));
    }

    public function getIcon(): BackedEnum
    {
        if ($this->apiKey) {
            return TablerIcon::Api;
        }

        if ($this->actor instanceof User) {
            return TablerIcon::User;
        }

        return $this->actor_id === null ? TablerIcon::DeviceDesktop : TablerIcon::UserOff;
    }

    public function getLabel(): string
    {
        $properties = $this->wrapProperties();

        return trans_choice('activity.'.str($this->event)->replace(':', '.'), array_key_exists('count', $properties) ? $properties['count'] : 1, $properties);
    }

    public function getIp(): ?string
    {
        return user()?->can('seeIps activityLog') ? $this->ip : null;
    }

    public function htmlable(): string
    {
        $user = $this->actor;
        if (!$user instanceof User) {
            $user = new User([
                'email' => 'system@pelican.dev',
                'username' => 'system',
            ]);
        }

        $avatarUrl = Filament::getUserAvatarUrl($user);
        $username = str($user->username)->stripTags();
        $ip = $this->getIp();
        $ip = $ip ? $ip . ' — ' : '';

        return "
            <div style='display: flex; align-items: center;'>
                <img width='50px' height='50px' src='{$avatarUrl}' style='margin-right: 15px; border-radius: 50%;' />

                <div>
                    <p>$username — $this->event</p>
                    <p>{$this->getLabel()}</p>
                    <p>$ip<span title='{$this->timestamp->format('M j, Y g:ia')}'>{$this->timestamp->diffForHumans()}</span></p>
                </div>
            </div>
        ";
    }

    /**
     * @return array<string, string>
     */
    public function wrapProperties(): array
    {
        if (!$this->properties || $this->properties->isEmpty()) {
            return [];
        }

        $properties = $this->properties->mapWithKeys(function ($value, $key) {
            if (!is_array($value)) {
                // Perform some directory normalization at this point.
                if ($key === 'directory') {
                    $value = str_replace('//', '/', '/' . trim($value, '/') . '/');
                }

                return [$key => str($value)->stripTags()->toString()];
            }

            $first = Arr::first($value);

            // Backwards compatibility for old logs
            if (is_array($first)) {
                return ["{$key}_count" => count($value)];
            }

            return [$key => str($first)->stripTags()->toString(), "{$key}_count" => count($value)];
        });

        $keys = $properties->keys()->filter(fn ($key) => Str::endsWith($key, '_count'))->values();
        if ($keys->containsOneItem()) {
            $properties = $properties->merge(['count' => $properties->get($keys[0])])->except([$keys[0]]);
        }

        return $properties->toArray();
    }

    /**
     * Determines if there are any log properties that we've not already exposed
     * in the response language string and that are not just the IP address or
     * the browser useragent.
     *
     * This is used by the front-end to selectively display an "additional metadata"
     * button that is pointless if there is nothing the user can't already see from
     * the event description.
     */
    public function hasAdditionalMetadata(): bool
    {
        if (!$this->properties || $this->properties->isEmpty()) {
            return false;
        }

        $properties = $this->wrapProperties();
        $event = trans_choice('activity.'.str($this->event)->replace(':', '.'), array_key_exists('count', $properties) ? $properties['count'] : 1);

        preg_match_all('/:(?<key>[\w.-]+\w)(?:[^\w:]?|$)/', $event, $matches);

        $exclude = array_merge($matches['key'], ['ip', 'useragent', 'using_sftp']);
        foreach ($this->properties->keys() as $key) {
            if (!in_array($key, $exclude, true)) {
                return true;
            }
        }

        return false;
    }
}
