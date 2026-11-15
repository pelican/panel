<?php

namespace App\Services\Backups;

use App\Models\Backup;
use App\Models\BackupHost;
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
        return new self(self::TYPE_S3, backupHostId: $backupHost->id, key: ltrim(trim($key), '/'));
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
