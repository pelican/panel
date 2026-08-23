<?php

namespace App\Http\Requests\Api\Client\Servers\Settings;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class DescriptionServerRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    /**
     * Returns the permissions string indicating which permission should be used to
     * validate that the authenticated user has permission to perform this action against
     * the given resource (server).
     */
    public function permission(): SubuserPermission
    {
        return SubuserPermission::SettingsDescription;
    }

    /**
     * The rules to apply when validating this request.
     */
    public function rules(): array
    {
        return [
            /** New description for the server. */
            'description' => 'string|nullable',
        ];
    }
}
