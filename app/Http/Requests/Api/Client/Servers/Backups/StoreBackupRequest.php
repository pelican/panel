<?php

namespace App\Http\Requests\Api\Client\Servers\Backups;

use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class StoreBackupRequest extends ClientApiRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::BackupCreate;
    }

    public function rules(): array
    {
        return [
            /** Name for the backup. A timestamped name is generated when this is left empty. */
            'name' => 'nullable|string|max:255',
            /** Lock the backup so it cannot be deleted or rotated out until it is unlocked. Only applied when the caller has the delete-backup permission; ignored otherwise. */
            'is_locked' => 'nullable|boolean',
            /** Newline separated list of paths to leave out of the backup, in the same format as `.gitignore`. */
            'ignored' => 'nullable|string',
        ];
    }
}
