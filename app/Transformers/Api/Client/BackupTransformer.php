<?php

namespace App\Transformers\Api\Client;

use App\Models\Backup;

class BackupTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return Backup::RESOURCE_NAME;
    }

    /**
     * @param  Backup  $backup
     */
    public function transform($backup): array
    {
        return [
            'uuid' => $backup->uuid,
            'is_successful' => $backup->is_successful,
            'is_locked' => $backup->is_locked,
            'is_scheduled' => $backup->is_scheduled,
            'name' => $backup->name,
            'ignored_files' => $backup->ignored_files,
            'checksum' => $backup->checksum,
            'bytes' => $backup->bytes,
            'created_at' => $backup->created_at->toAtomString(),
            'completed_at' => $backup->completed_at ? $backup->completed_at->toAtomString() : null,
        ];
    }
}
