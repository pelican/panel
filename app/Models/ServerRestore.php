<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pending or finished restore of a backup into a server that was created
 * from it. The source is stored rather than a download link, because links
 * expire before the new server has finished installing.
 *
 * @property int $id
 * @property string $uuid
 * @property int $server_id
 * @property int|null $backup_id
 * @property int|null $backup_host_id
 * @property string|null $source_key
 * @property string|null $source_url
 * @property string $status
 * @property string|null $callback_uuid
 * @property bool $start_on_completion
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server $server
 * @property-read Backup|null $backup
 * @property-read BackupHost|null $backupHost
 */
class ServerRestore extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'start_on_completion' => false,
    ];

    protected function casts(): array
    {
        return [
            'source_url' => 'encrypted',
            'start_on_completion' => 'bool',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    public function backupHost(): BelongsTo
    {
        return $this->belongsTo(BackupHost::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
