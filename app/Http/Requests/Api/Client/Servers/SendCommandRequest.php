<?php

namespace App\Http\Requests\Api\Client\Servers;

use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class SendCommandRequest extends ClientApiRequest
{
    /**
     * Determine if the API user has permission to perform this action.
     */
    public function permission(): SubuserPermission
    {
        return SubuserPermission::ControlConsole;
    }

    /**
     * Rules to validate this request against.
     */
    public function rules(): array
    {
        return [
            /** Command to write to the server's console, without a trailing newline. */
            'command' => 'required|string|min:1',
        ];
    }
}
