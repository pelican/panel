<?php

namespace App\Http\Requests\Api\Application\Servers;

class GetServersRequest extends GetServerRequest
{
    public function rules(): array
    {
        return [
            /** Text matched against the servers' name, description, UUID and external ID. */
            'search' => 'string|max:100',
        ];
    }
}
