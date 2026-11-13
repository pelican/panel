<?php

namespace App\Models;

use App\Enums\ServerTransferBatchItemStatus;
use App\Enums\ServerTransferBatchStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A queue of servers moving from one node to another, one server at a time.
 *
 * @property int $id
 * @property int $old_node
 * @property int $new_node
 * @property int|null $user_id
 * @property ServerTransferBatchStatus $status
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Node $oldNode
 * @property-read Node $newNode
 * @property-read User|null $user
 * @property-read Collection<int, ServerTransferBatchItem> $items
 * @property-read Collection<int, ServerTransferBatchItem> $failedItems
 * @property-read ServerTransferBatchItem|null $activeItem
 * @property-read int|null $finished_items_count
 */
class ServerTransferBatch extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'old_node' => 'int',
            'new_node' => 'int',
            'user_id' => 'int',
            'status' => ServerTransferBatchStatus::class,
            'finished_at' => 'datetime',
        ];
    }

    public function oldNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'old_node');
    }

    public function newNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'new_node');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ServerTransferBatchItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ServerTransferBatchItem::class, 'batch_id');
    }

    /** @return HasMany<ServerTransferBatchItem, $this> */
    public function failedItems(): HasMany
    {
        return $this->items()->where('status', ServerTransferBatchItemStatus::Failed);
    }

    /**
     * The one server this batch is currently working on.
     *
     * @return HasOne<ServerTransferBatchItem, $this>
     */
    public function activeItem(): HasOne
    {
        return $this->hasOne(ServerTransferBatchItem::class, 'batch_id')
            ->whereIn('status', [ServerTransferBatchItemStatus::Stopping, ServerTransferBatchItemStatus::Transferring]);
    }
}
