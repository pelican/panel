<?php

namespace App\Http\Requests\Api\Application\Servers;

use App\Models\Server;

class UpdateServerDetailsRequest extends ServerWriteRequest
{
    /**
     * Rules to apply to a server details update request.
     */
    public function rules(): array
    {
        $rules = $this->route() ? Server::getRulesForUpdate($this->parameter('server', Server::class)) : Server::getRules();

        return [
            /** Identifier from your own system, stored alongside the server so you can match the two up later. */
            'external_id' => $rules['external_id'],
            /** Name the server is shown under in the Panel. */
            'name' => $rules['name'],
            /** ID of the user who owns the server. Changing this transfers ownership. */
            'user' => $rules['owner_id'],
            /** Free form text describing the server. */
            'description' => array_merge(['nullable'], $rules['description']),
        ];
    }

    /**
     * Convert the posted data into the correct format that is expected by the application.
     *
     * @return array<array-key, string>
     */
    public function validated($key = null, $default = null): array
    {
        return [
            'external_id' => $this->input('external_id'),
            'name' => $this->input('name'),
            'owner_id' => $this->input('user'),
            'description' => $this->input('description'),
        ];
    }

    /**
     * Rename some attributes in error messages to clarify the field
     * being discussed.
     *
     * @return array<array-key, string>
     */
    public function attributes(): array
    {
        return [
            'user' => 'User ID',
            'name' => 'Server Name',
        ];
    }
}
