<?php

namespace App\Http\Requests\Api\Client\Servers\Files;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class ChmodFilesRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::FileUpdate;
    }

    public function rules(): array
    {
        return [
            /** Directory the files live in, relative to the server root. */
            'root' => 'required|nullable|string',
            /** Files to change the permissions of. */
            'files' => 'required|array',
            /** Name of the file, relative to `root`. */
            'files.*.file' => 'required|string',
            /** New permissions as an octal number, such as `644`. */
            'files.*.mode' => 'required|numeric',
        ];
    }
}
