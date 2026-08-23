<?php

namespace App\Http\Requests\Api\Application\DatabaseHosts;

use App\Models\DatabaseHost;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;

// Attributes are not inherited, so these repeat the parent's against the same descriptions.
#[BodyParameter('name', description: self::FIELDS['name'])]
#[BodyParameter('host', description: self::FIELDS['host'])]
#[BodyParameter('port', description: self::FIELDS['port'])]
#[BodyParameter('username', description: self::FIELDS['username'])]
#[BodyParameter('password', description: self::FIELDS['password'])]
#[BodyParameter('node_ids', description: self::FIELDS['node_ids'])]
class UpdateDatabaseHostRequest extends StoreDatabaseHostRequest
{
    /** @return array<string, string|array<string|\Stringable|ValidationRule>> */
    public function rules(?array $rules = null): array
    {
        // No route to read the host off of when the rules are being inspected rather than applied,
        // which is how the API documentation is generated.
        if (!$this->route()) {
            return $rules ?? DatabaseHost::getRules();
        }

        /** @var DatabaseHost $databaseHost */
        $databaseHost = $this->route()->parameter('database_host');

        return $rules ?? DatabaseHost::getRulesForUpdate($databaseHost);
    }
}
