<?php

namespace App\Http\Requests\Api\Client\Servers\Files;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class GetFileContentsRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    /**
     * Returns the permissions string indicating which permission should be used to
     * validate that the authenticated user has permission to perform this action aganist
     * the given resource (server).
     */
    public function permission(): SubuserPermission
    {
        return SubuserPermission::FileReadContent;
    }

    public function rules(): array
    {
        return [
            /** Path of the file to read, relative to the server root. */
            'file' => 'required|string',
        ];
    }
}
