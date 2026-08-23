<?php

namespace App\Models;

use App\Contracts\Validatable;
use App\Traits\HasValidation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $server_id
 * @property array<string, string|int|bool>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server $server
 * @property-read User $user
 *
 * @method static \Database\Factories\ServerUserSettingsFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerUserSettings newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerUserSettings newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerUserSettings query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerUserSettings whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerUserSettings whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerUserSettings whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerUserSettings whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerUserSettings whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerUserSettings whereUserId($value)
 */
class ServerUserSettings extends Model implements Validatable
{
    use HasFactory;
    use HasValidation;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'server_user_settings';

    protected $table = 'server_user_settings';

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** @var array<array-key, string[]> */
    public static array $validationRules = [
        'user_id' => ['required', 'numeric', 'exists:users,id'],
        'server_id' => ['required', 'numeric', 'exists:servers,id'],
        'settings' => ['nullable', 'array'],
        'settings.manual_backup_notifications' => ['boolean'],
        'settings.scheduled_backup_notifications' => ['boolean'],
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'server_id' => 'int',
            'settings' => 'array',
        ];
    }

    /**
     * Gets the server these settings are associated with.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Gets the user these settings are associated with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
