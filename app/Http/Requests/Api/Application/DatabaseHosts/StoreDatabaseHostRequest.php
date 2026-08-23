<?php

namespace App\Http\Requests\Api\Application\DatabaseHosts;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\DatabaseHost;
use App\Services\Acl\Api\AdminAcl;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;

// The rules come straight off the model, so there is no rules array to hang comments on. These
// describe the fields without touching the inferred types or the validation itself. Attributes
// are not inherited, so UpdateDatabaseHostRequest repeats them against the same descriptions.
#[BodyParameter('name', description: self::FIELDS['name'])]
#[BodyParameter('host', description: self::FIELDS['host'])]
#[BodyParameter('port', description: self::FIELDS['port'])]
#[BodyParameter('username', description: self::FIELDS['username'])]
#[BodyParameter('password', description: self::FIELDS['password'])]
#[BodyParameter('node_ids', description: self::FIELDS['node_ids'])]
class StoreDatabaseHostRequest extends ApplicationApiRequest
{
    /** @var array<string, string> */
    public const FIELDS = [
        'name' => 'Name the database host is shown under in the Panel.',
        'host' => 'Hostname or IP address the Panel connects to the database server on.',
        'port' => 'Port the database server listens on.',
        'username' => 'Account the Panel uses to create databases and users on the host. It needs privileges to do both.',
        'password' => 'Password for that account. It is stored encrypted and never returned by the API.',
        'node_ids' => 'IDs of the nodes allowed to use this database host.',
    ];

    protected ?string $resource = DatabaseHost::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /**
     * @param  array<string, string|array<string|\Stringable|ValidationRule>>|null  $rules
     * @return array<string, string|array<string|\Stringable|ValidationRule>>
     */
    public function rules(?array $rules = null): array
    {
        return $rules ?? DatabaseHost::getRules();
    }
}
