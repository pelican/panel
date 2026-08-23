<?php

namespace App\Http\Requests\Api\Client\Servers\Files;

use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class CompressFilesRequest extends ClientApiRequest
{
    /**
     * Checks that the authenticated user is allowed to create archives for this server.
     */
    public function permission(): SubuserPermission
    {
        return SubuserPermission::FileArchive;
    }

    public function rules(): array
    {
        return [
            /** Directory the files live in, relative to the server root. The archive is written here too. */
            'root' => 'sometimes|nullable|string',
            /** Files and directories to put in the archive. */
            'files' => 'required|array',
            /** Name of a file or directory, relative to `root`. */
            'files.*' => 'string',
            /** Name for the archive. A timestamped name is generated when this is left empty. */
            'name' => 'sometimes|nullable|string',
            /** Archive format to write. Defaults to `tar.gz`. */
            'extension' => 'sometimes|in:zip,tgz,tar.gz,txz,tar.xz,tbz2,tar.bz2',
        ];
    }
}
