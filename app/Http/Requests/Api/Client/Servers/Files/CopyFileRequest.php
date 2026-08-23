<?php

namespace App\Http\Requests\Api\Client\Servers\Files;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class CopyFileRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::FileCreate;
    }

    public function rules(): array
    {
        return [
            /** Path of the file or directory to copy, relative to the server root. The copy is made alongside it. */
            'location' => 'required|string',
        ];
    }
}
