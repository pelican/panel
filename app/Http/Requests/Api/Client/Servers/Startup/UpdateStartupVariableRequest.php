<?php

namespace App\Http\Requests\Api\Client\Servers\Startup;

use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class UpdateStartupVariableRequest extends ClientApiRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::StartupUpdate;
    }

    /**
     * The actual validation of the variable's value will happen inside the controller.
     */
    public function rules(): array
    {
        return [
            /** Name of the egg variable to change, as shown in the server's startup settings. */
            'key' => 'required|string',
            /** New value for the variable. */
            'value' => 'present',
        ];
    }
}
