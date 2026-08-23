<?php

namespace App\Http\Requests\Api\Client\Servers\Databases;

use App\Contracts\Http\ClientPermissionsRequest;
use App\Enums\SubuserPermission;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\Database;
use App\Models\Server;
use App\Services\Databases\DatabaseManagementService;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Webmozart\Assert\Assert;

class StoreDatabaseRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::DatabaseCreate;
    }

    public function rules(): array
    {
        $server = $this->route()->parameter('server');

        Assert::isInstanceOf($server, Server::class);

        return [
            /** Name of the database to create. The final name is prefixed with the server ID, and must not already be in use by this server. */
            'database' => [
                'required',
                'alpha_dash',
                'min:3',
                'max:48',
                // Yes, I am aware that you could have the same database name across two unique hosts. However,
                // I don't really care about that for this validation. We just want to make sure it is unique to
                // the server itself. No need for complexity.
                Rule::unique('databases')->where(function (Builder $query) use ($server) {
                    $query->where('server_id', $server->id)
                        ->where('database', DatabaseManagementService::generateUniqueDatabaseName($this->input('database'), $server->id));
                }),
            ],
            /** Addresses the database user may connect from. Use `%` to allow any address. */
            'remote' => Database::getRulesForField('remote'),
        ];
    }

    /**
     * @return array<array-key, string>
     */
    public function messages(): array
    {
        return [
            'database.unique' => 'The database name you have selected is already in use by this server.',
        ];
    }
}
