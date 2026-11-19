<?php

namespace App\Services\Backups;

use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Where the files for a server created from a backup come from: a backup the
 * Panel knows about, an object in an S3 backup host, or a URL.
 */
final readonly class RestoreSource
{
    public const TYPE_BACKUP = 'backup';

    public const TYPE_S3 = 's3';

    public const TYPE_URL = 'url';

    private function __construct(
        public string $type,
        public ?int $backupId = null,
        public ?int $backupHostId = null,
        public ?string $key = null,
        public ?string $url = null,
    ) {}

    public static function backup(Backup $backup): self
    {
        return new self(self::TYPE_BACKUP, backupId: $backup->id);
    }

    public static function s3(BackupHost $backupHost, string $key): self
    {
        return new self(self::TYPE_S3, backupHostId: $backupHost->id, key: trim($key));
    }

    public static function url(string $url): self
    {
        $url = trim($url);
        if (!in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http and https links can be restored from.');
        }

        return new self(self::TYPE_URL, url: $url);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return match ($data['type'] ?? null) {
            self::TYPE_BACKUP => new self(self::TYPE_BACKUP, backupId: (int) $data['backup_id']),
            self::TYPE_S3 => new self(self::TYPE_S3, backupHostId: (int) $data['backup_host_id'], key: (string) $data['key']),
            self::TYPE_URL => self::url((string) $data['url']),
            default => throw new InvalidArgumentException('Unknown restore source.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'backup_id' => $this->backupId,
            'backup_host_id' => $this->backupHostId,
            'key' => $this->key,
            'url' => $this->url,
        ], fn ($value) => $value !== null);
    }

    public function getBackup(): ?Backup
    {
        return $this->backupId ? Backup::query()->find($this->backupId) : null;
    }

    public function getBackupHost(): ?BackupHost
    {
        return $this->backupHostId ? BackupHost::query()->find($this->backupHostId) : null;
    }

    /**
     * Whether the user may restore from this source. Administrators limited to
     * some nodes may only use the backups and backup hosts of those nodes, and
     * may not reach another node's backup through an S3 key.
     */
    public function isAccessibleBy(?User $user): bool
    {
        return match ($this->type) {
            self::TYPE_BACKUP => $this->backupId !== null && Backup::query()->restorable()->accessibleBy($user)->whereKey($this->backupId)->exists(),
            self::TYPE_S3 => $this->isS3SourceAccessibleBy($user),
            // Links can only point at public addresses, see BackupConfigResolver::isPublicUrl().
            default => true,
        };
    }

    private function isS3SourceAccessibleBy(?User $user): bool
    {
        if ($this->backupHostId === null || $this->key === null || $user === null) {
            return false;
        }

        if (!BackupHost::query()->where('schema', 's3')->accessibleBy($user)->whereKey($this->backupHostId)->exists()) {
            return false;
        }

        if ($user->isRootAdmin()) {
            return true;
        }

        // Keys of Panel backups are "{server uuid}/{backup uuid}.tar.gz". A key that
        // names a known backup or server is only usable if that one is accessible.
        $segments = explode('/', trim($this->key, '/'));
        $backupUuid = Str::beforeLast((string) end($segments), '.tar.gz');
        $serverUuid = count($segments) >= 2 ? $segments[count($segments) - 2] : null;

        if (Str::isUuid($backupUuid) && Backup::query()->where('uuid', $backupUuid)->exists()) {
            return Backup::query()->accessibleBy($user)->where('uuid', $backupUuid)->exists();
        }

        if ($serverUuid !== null && Str::isUuid($serverUuid) && ($server = Server::query()->with('node')->where('uuid', $serverUuid)->first())) {
            return $user->canTarget($server->node);
        }

        return true;
    }

    /**
     * A short description of the source for display.
     */
    public function label(): string
    {
        return match ($this->type) {
            self::TYPE_BACKUP => ($backup = $this->getBackup()) ? "$backup->name ({$backup->serverDisplayName()})" : trans('admin/backup.restore.missing_backup'),
            self::TYPE_S3 => ($this->getBackupHost()->name ?? 'S3') . ": $this->key",
            default => (string) parse_url((string) $this->url, PHP_URL_HOST) . (string) parse_url((string) $this->url, PHP_URL_PATH),
        };
    }
}
