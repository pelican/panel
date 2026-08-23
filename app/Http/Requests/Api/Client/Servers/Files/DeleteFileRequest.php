<?php

namespace App\Http\Requests\Api\Client\Servers\Files;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class DeleteFileRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::FileDelete;
    }

    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            /** Directory the files live in, relative to the server root. */
            'root' => 'required|nullable|string',
            /** Files and directories to delete. Directories are removed with everything inside them. */
            'files' => 'required|array',
            /** Name of a file or directory, relative to `root`. */
            'files.*' => 'string',
        ];
    }
}
