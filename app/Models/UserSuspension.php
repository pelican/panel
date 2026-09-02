<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $actor_id
 * @property string $reason
 * @property bool $suspend_servers
 * @property Carbon|null $lifted_at
 * @property int|null $lifted_by
 */
class UserSuspension extends Model
{
    protected $fillable = [
        'user_id',
        'actor_id',
        'reason',
        'suspend_servers',
        'lifted_at',
        'lifted_by',
    ];

    protected function casts(): array
    {
        return [
            'suspend_servers' => 'boolean',
            'lifted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    /** @return HasMany<UserSuspensionServer, $this> */
    public function servers(): HasMany
    {
        return $this->hasMany(UserSuspensionServer::class);
    }
}
