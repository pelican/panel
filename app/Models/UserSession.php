<?php

namespace App\Models;

use App\Enums\TablerIcon;
use App\Helpers\UserAgent;
use App\Services\Users\UserSessionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Throwable;

/**
 * A device (browser) a user is signed in on.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $session_id
 * @property string $token_hash
 * @property string|null $name
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property bool $remember
 * @property Carbon|null $created_at
 * @property Carbon|null $last_active_at
 * @property-read User $user
 *
 * @method static Builder<static>|UserSession active()
 * @method static Builder<static>|UserSession query()
 */
class UserSession extends Model
{
    use HasFactory;
    use Prunable;

    public const UPDATED_AT = null;

    protected $table = 'user_sessions';

    protected $fillable = [
        'session_id',
        'token_hash',
        'name',
        'ip_address',
        'user_agent',
        'remember',
        'last_active_at',
    ];

    protected $hidden = [
        'token_hash',
        'session_id',
    ];

    protected function casts(): array
    {
        return [
            'remember' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Days of inactivity after which a device is signed out, or null if disabled.
     */
    public static function inactivityDays(): ?int
    {
        $days = (int) config('panel.auth.session_inactivity_days');

        return $days > 0 ? $days : null;
    }

    /**
     * Devices that could still be signed in: not past the inactivity limit, and, without
     * "remember me", not past the session lifetime either.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        if ($days = static::inactivityDays()) {
            $query->where('last_active_at', '>', now()->subDays($days));
        }

        $query->where(fn (Builder $query) => $query
            ->where('remember', true)
            ->orWhere('last_active_at', '>', static::sessionExpiryCutoff()));
    }

    /**
     * Devices without "remember me" that were last active before this have an expired session.
     * last_active_at is only written every TOUCH_INTERVAL_SECONDS, so that much slack is added
     * on top of the session lifetime.
     */
    public static function sessionExpiryCutoff(): Carbon
    {
        return now()
            ->subMinutes((int) config('session.lifetime', 120))
            ->subSeconds(UserSessionService::TOUCH_INTERVAL_SECONDS);
    }

    public function isInactive(): bool
    {
        $days = static::inactivityDays();

        return $days !== null && $this->last_active_at !== null && $this->last_active_at->lte(now()->subDays($days));
    }

    public function userAgent(): UserAgent
    {
        return UserAgent::parse($this->user_agent);
    }

    /**
     * Label detected from the user agent, e.g. "Firefox on Windows".
     */
    public function detectedLabel(): string
    {
        return $this->userAgent()->label()
            ?? ($this->user_agent ? Str::limit($this->user_agent, 48) : trans('profile.sessions.unknown_device'));
    }

    public function displayName(): string
    {
        return $this->name ?: $this->detectedLabel();
    }

    public function icon(): TablerIcon
    {
        return match ($this->userAgent()->type) {
            UserAgent::TYPE_MOBILE => TablerIcon::DeviceMobile,
            UserAgent::TYPE_TABLET => TablerIcon::DeviceTablet,
            UserAgent::TYPE_DESKTOP => TablerIcon::DeviceDesktop,
            default => TablerIcon::QuestionMark,
        };
    }

    /**
     * Destroys the Laravel session behind this device, if the session driver supports it.
     * The tracking middleware signs the device out anyway once the row is gone.
     */
    public function destroyUnderlyingSession(): void
    {
        if (!$this->session_id) {
            return;
        }

        try {
            Session::getHandler()->destroy($this->session_id);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        $days = static::inactivityDays();

        return static::query()->where(function (Builder $query) use ($days) {
            if ($days) {
                $query->where('last_active_at', '<=', now()->subDays($days));
            }

            $query->orWhereNull('last_active_at')
                ->orWhere(fn (Builder $query) => $query
                    ->where('remember', false)
                    ->where('last_active_at', '<=', static::sessionExpiryCutoff()));
        });
    }

    protected function pruning(): void
    {
        $this->destroyUnderlyingSession();
    }
}
