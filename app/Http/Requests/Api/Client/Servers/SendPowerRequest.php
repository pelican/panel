<?php

namespace App\Http\Requests\Api\Client\Servers;

use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;

class SendPowerRequest extends ClientApiRequest
{
    /**
     * Determine if the user has permission to send a power command to a server.
     */
    public function permission(): SubuserPermission
    {
        // Fallback for invalid signals
        return SubuserPermission::fromPowerSignal((string) $this->input('signal')) ?? SubuserPermission::WebsocketConnect;
    }

    /**
     * Rules to validate this request against.
     */
    public function rules(): array
    {
        return [
            /** Power action to send. `kill` stops the container immediately and may lose unsaved data. */
            'signal' => 'required|string|in:start,stop,restart,kill',
        ];
    }
}
