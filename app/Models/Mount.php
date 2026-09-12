<?php

namespace App\Models;

use App\Contracts\Validatable;
use App\Traits\HasValidation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property string $source
 * @property string $target
 * @property bool $read_only
 * @property bool $user_mountable
 * @property-read Collection<int, Egg> $eggs
 * @property-read int|null $eggs_count
 * @property-read Collection<int, Node> $nodes
 * @property-read int|null $nodes_count
 * @property-read Collection<int, Server> $servers
 * @property-read int|null $servers_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount whereReadOnly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount whereTarget($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount whereUserMountable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mount whereUuid($value)
 */
class Mount extends Model implements Validatable
{
    use HasValidation { getRules as getValidationRules; }

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'mount';

    /**
     * Fields that are not mass assignable.
     *
     * @var string[]
     */
    protected $guarded = ['id'];

    /**
     * The database columns carry no defaults, so creating a mount without
     * these booleans (the API marks them 'sometimes') would fail the insert.
     *
     * @var array<string, bool>
     */
    protected $attributes = [
        'read_only' => false,
        'user_mountable' => false,
    ];

    /**
     * Rules verifying that the data being stored matches the expectations of the database.
     *
     * @var array<array-key, string[]>
     */
    public static array $validationRules = [
        'name' => ['required', 'string', 'min:2', 'max:64', 'unique:mounts,name'],
        'description' => ['nullable', 'string', 'max:255'],
        'source' => ['required', 'string'],
        'target' => ['required', 'string'],
        'read_only' => ['sometimes', 'boolean'],
        'user_mountable' => ['sometimes', 'boolean'],
    ];

    /**
     * Implement language verification by overriding Eloquence's gather rules function.
     *
     * @return array<array-key, string[]>
     */
    public static function getRules(): array
    {
        $rules = self::getValidationRules();
        $rules['source'][] = 'not_in:' . implode(',', Mount::$invalidSourcePaths);
        $rules['target'][] = 'not_in:' . implode(',', Mount::$invalidTargetPaths);

        return $rules;
    }

    /**
     * Disable timestamps on this model.
     */
    public $timestamps = false;

    /**
     * Blacklisted source paths.
     *
     * @var string[]
     */
    public static array $invalidSourcePaths = [
        '/etc/pelican',
        '/var/lib/pelican/volumes',
        '/srv/daemon-data',
    ];

    /**
     * Blacklisted target paths.
     *
     * @var string[]
     */
    public static array $invalidTargetPaths = [
        '/home/container',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'read_only' => 'bool',
            'user_mountable' => 'bool',
        ];
    }

    /**
     * Returns all eggs that have this mount assigned.
     */
    public function eggs(): MorphToMany
    {
        return $this->morphedByMany(Egg::class, 'mountable');
    }

    /**
     * Returns all nodes that have this mount assigned.
     */
    public function nodes(): MorphToMany
    {
        return $this->morphedByMany(Node::class, 'mountable');
    }

    /**
     * Returns all servers that have this mount assigned.
     */
    public function servers(): MorphToMany
    {
        return $this->morphedByMany(Server::class, 'mountable');
    }
}
