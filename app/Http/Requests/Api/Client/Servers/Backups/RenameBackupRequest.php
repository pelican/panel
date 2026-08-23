<?php

namespace App\Http\Requests\Api\Client\Servers\Backups;

use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class RenameBackupRequest extends ClientApiRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::BackupDelete;
    }

    public function rules(): array
    {
        return [
            /** New name for the backup. */
            'name' => 'required|string|max:255',
        ];
    }
}
