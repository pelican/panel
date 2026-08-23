<?php

namespace App\Http\Requests\Api\Client\Servers\Files;

use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class ListFilesRequest extends ClientApiRequest
{
    /**
     * Check that the user making this request to the API is authorized to list all
     * the files that exist for a given server.
     */
    public function permission(): SubuserPermission
    {
        return SubuserPermission::FileRead;
    }

    public function rules(): array
    {
        return [
            /** Directory to list, relative to the server root. Defaults to the server root. */
            'directory' => 'sometimes|nullable|string',
        ];
    }
}
