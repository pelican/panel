<?php

namespace App\Models;

use App\Exceptions\Service\Allocation\ServerUsingAllocationException;
use App\Traits\HasValidation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\Allocation.
 *
 * @property int $id
 * @property int $node_id
 * @property string $ip
 * @property int $port
 * @property int|null $server_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $ip_alias
 * @property string|null $notes
 * @property bool $is_locked
 * @property bool $show_port
 * @property-read string $address
 * @property-read string $alias
 * @property-read string $display_address
 * @property-read bool $has_alias
 * @property-read Node $node
 * @property-read Server|null $server
 *
 * @method static \Database\Factories\AllocationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Allocation newModelQuery()
 * @method static Builder<static>|Allocation newQuery()
 * @method static Builder<static>|Allocation query()
 * @method static Builder<static>|Allocation whereCreatedAt($value)
 * @method static Builder<static>|Allocation whereId($value)
 * @method static Builder<static>|Allocation whereIp($value)
 * @method static Builder<static>|Allocation whereIpAlias($value)
 * @method static Builder<static>|Allocation whereIsLocked($value)
 * @method static Builder<static>|Allocation whereNodeId($value)
 * @method static Builder<static>|Allocation whereNotes($value)
 * @method static Builder<static>|Allocation wherePort($value)
 * @method static Builder<static>|Allocation whereServerId($value)
 * @method static Builder<static>|Allocation whereShowPort($value)
 * @method static Builder<static>|Allocation whereUpdatedAt($value)
 */
class Allocation extends Model
{
    use HasFactory;
    use HasValidation;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal. Also used as name for api key permissions.
     */
    public const RESOURCE_NAME = 'allocation';

    /**
     * Attribute resets applied whenever an allocation is released back to the pool,
     * so per-server state never carries over to the next server. Query-builder
     * updates bypass the model's `updating` hook, so release paths must apply
     * these explicitly.
     *
     * @var array<string, mixed>
     */
    public const RELEASE_ATTRIBUTES = [
        'server_id' => null,
        'notes' => null,
        'is_locked' => false,
        'show_port' => true,
    ];

    protected $attributes = [
        'is_locked' => false,
        'show_port' => true,
    ];

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** @var array<array-key, string[]> */
    public static array $validationRules = [
        'node_id' => ['required', 'exists:nodes,id'],
        'ip' => ['required', 'ip'],
        'port' => ['required', 'numeric', 'between:1024,65535'],
        'ip_alias' => ['nullable', 'string'],
        'server_id' => ['nullable', 'exists:servers,id'],
        'notes' => ['nullable', 'string', 'max:256'],
        'is_locked' => ['boolean'],
        'show_port' => ['boolean'],
    ];

    protected static function booted(): void
    {
        static::updating(function (self $allocation) {
            if (is_null($allocation->server_id)) {
                $allocation->is_locked = false;
                $allocation->show_port = true;
            }
        });

        static::deleting(function (self $allocation) {
            throw_if($allocation->server_id, new ServerUsingAllocationException(trans('exceptions.allocations.server_using')));
        });
    }

    protected function casts(): array
    {
        return [
            'node_id' => 'integer',
            'port' => 'integer',
            'server_id' => 'integer',
            'is_locked' => 'bool',
            'show_port' => 'bool',
        ];
    }

    /**
     * Accessor to automatically provide the IP alias if defined.
     */
    public function getAliasAttribute(?string $value): string
    {
        return (is_null($this->ip_alias)) ? $this->ip : $this->ip_alias;
    }

    /**
     * Accessor to quickly determine if this allocation has an alias.
     */
    public function getHasAliasAttribute(?string $value): bool
    {
        return !is_null($this->ip_alias);
    }

    /** @return Attribute<string, never> */
    protected function address(): Attribute
    {
        return Attribute::make(
            get: fn () => (is_ipv6($this->alias) ? "[$this->alias]" : $this->alias) . ":$this->port",
        );
    }

    /** @return Attribute<string, never> */
    protected function displayAddress(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->show_port ? $this->address : $this->alias,
        );
    }

    /**
     * Gets information for the server associated with this allocation.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Return the Node model associated with this allocation.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
