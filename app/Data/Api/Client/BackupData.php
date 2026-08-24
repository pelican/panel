<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Models\Backup;

final class BackupData extends ApiResource
{
    /**
     * @param  string[]  $ignored_files
     */
    public function __construct(
        public string $uuid,
        public bool $is_successful,
        public bool $is_locked,
        public bool $is_scheduled,
        public string $name,
        public array $ignored_files,
        public ?string $checksum,
        public int $bytes,
        public string $created_at,
        public ?string $completed_at,
    ) {}

    public static function getResourceName(): string
    {
        return Backup::RESOURCE_NAME;
    }

    public static function fromModel(Backup $model): static
    {
        return new self(
            uuid: $model->uuid,
            is_successful: $model->is_successful,
            is_locked: $model->is_locked,
            is_scheduled: $model->is_scheduled,
            name: $model->name,
            ignored_files: $model->ignored_files,
            checksum: $model->checksum,
            bytes: $model->bytes,
            created_at: $model->created_at->toAtomString(),
            completed_at: $model->completed_at ? $model->completed_at->toAtomString() : null,
        );
    }
}
