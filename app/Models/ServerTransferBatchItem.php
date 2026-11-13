<?php

namespace App\Models;

use App\Enums\ServerTransferBatchItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $batch_id
 * @property int $server_id
 * @property int|null $server_transfer_id
 * @property string|null $new_ip
 * @property bool $move_backups
 * @property ServerTransferBatchItemStatus $status
 * @property bool $was_running
 * @property string|null $error
 * @property Carbon|null $stop_requested_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ServerTransferBatch $batch
 * @property-read Server $server
 * @property-read ServerTransfer|null $transfer
 */
class ServerTransferBatchItem extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'batch_id' => 'int',
            'server_id' => 'int',
            'server_transfer_id' => 'int',
            'status' => ServerTransferBatchItemStatus::class,
            'move_backups' => 'bool',
            'was_running' => 'bool',
            'stop_requested_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ServerTransferBatch::class, 'batch_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(ServerTransfer::class, 'server_transfer_id');
    }
}
