<?php

namespace App\Http\Requests\Api\Client\Servers\Files;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class PullFileRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::FileCreate;
    }

    public function rules(): array
    {
        return [
            /** URL the file is downloaded from. */
            'url' => 'required|string|url',
            /** Directory the file is saved in, relative to the server root. */
            'directory' => 'nullable|string',
            /** Name to save the file under. Taken from the URL when left empty. */
            'filename' => 'nullable|string',
            /** Take the file name from the response's `Content-Disposition` header instead of the URL. */
            'use_header' => 'boolean',
            /** Wait for the download to finish before responding rather than running it in the background. */
            'foreground' => 'boolean',
        ];
    }
}
