<?php

namespace App\Models;

use App\Contracts\Validatable;
use App\Traits\HasValidation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $server_id
 * @property bool|null $successful
 * @property int $old_node
 * @property int $new_node
 * @property int|null $old_allocation
 * @property int|null $new_allocation
 * @property int[]|null $old_additional_allocations
 * @property int[]|null $new_additional_allocations
 * @property string[]|null $backup_uuids
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $archived
 * @property-read Node|null $newNode
 * @property-read Node|null $oldNode
 * @property-read Server $server
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereArchived($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereBackupUuids($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereNewAdditionalAllocations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereNewAllocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereNewNode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereOldAdditionalAllocations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereOldAllocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereOldNode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereSuccessful($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerTransfer whereUpdatedAt($value)
 */
class ServerTransfer extends Model implements Validatable
{
    use HasFactory;
    use HasValidation;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'server_transfer';

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** @var array<array-key, string[]> */
    public static array $validationRules = [
        'server_id' => ['required', 'numeric', 'exists:servers,id'],
        'old_node' => ['required', 'numeric'],
        'new_node' => ['required', 'numeric'],
        'old_allocation' => ['nullable', 'numeric'],
        'new_allocation' => ['nullable', 'numeric'],
        'old_additional_allocations' => ['nullable', 'array'],
        'old_additional_allocations.*' => ['numeric'],
        'new_additional_allocations' => ['nullable', 'array'],
        'new_additional_allocations.*' => ['numeric'],
        'backup_uuids' => ['nullable', 'array'],
        'backup_uuids.*' => ['string'],
        'successful' => ['sometimes', 'nullable', 'boolean'],
    ];

    protected function casts(): array
    {
        return [
            'server_id' => 'int',
            'old_node' => 'int',
            'new_node' => 'int',
            'old_allocation' => 'int',
            'new_allocation' => 'int',
            'old_additional_allocations' => 'array',
            'new_additional_allocations' => 'array',
            'backup_uuids' => 'array',
            'successful' => 'bool',
            'archived' => 'bool',
        ];
    }

    /**
     * Gets the server associated with a server transfer.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Gets the source node associated with a server transfer.
     */
    public function oldNode(): HasOne
    {
        return $this->hasOne(Node::class, 'id', 'old_node');
    }

    /**
     * Gets the target node associated with a server transfer.
     */
    public function newNode(): HasOne
    {
        return $this->hasOne(Node::class, 'id', 'new_node');
    }
}
