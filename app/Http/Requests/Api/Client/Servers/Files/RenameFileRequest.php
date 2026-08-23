<?php

namespace App\Http\Requests\Api\Client\Servers\Files;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class RenameFileRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    /**
     * The permission the user is required to have in order to perform this
     * request action.
     */
    public function permission(): SubuserPermission
    {
        return SubuserPermission::FileUpdate;
    }

    public function rules(): array
    {
        return [
            /** Directory the files live in, relative to the server root. */
            'root' => 'required|nullable|string',
            /** Files and directories to rename. Renaming across directories moves the file. */
            'files' => 'required|array',
            /** A single rename, given as its `from` and `to` names. */
            'files.*' => 'array',
            /** New name of the file, relative to `root`. */
            'files.*.to' => 'required|string',
            /** Current name of the file, relative to `root`. */
            'files.*.from' => 'required|string',
        ];
    }
}
