<?php

namespace App\Http\Requests\Api\Client\Servers\Subusers;

use App\Enums\SubuserPermission;

class UpdateSubuserRequest extends SubuserRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::UserUpdate;
    }

    public function rules(): array
    {
        return [
            /** Permissions the subuser is granted on this server. Permissions left out of this list are revoked. */
            'permissions' => 'required|array',
            /** A permission key, such as `control.console` or `file.read`. */
            'permissions.*' => 'string',
        ];
    }
}
