<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_suspension_id
 * @property int $server_id
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $suspended_at
 * @property Carbon|null $unsuspended_at
 */
class UserSuspensionServer extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNSUSPENDED = 'unsuspended';

    protected $fillable = [
        'server_id',
        'status',
        'error',
        'suspended_at',
        'unsuspended_at',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'unsuspended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<UserSuspension, $this> */
    public function suspension(): BelongsTo
    {
        return $this->belongsTo(UserSuspension::class, 'user_suspension_id');
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
