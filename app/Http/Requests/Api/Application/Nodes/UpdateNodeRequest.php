<?php

namespace App\Http\Requests\Api\Application\Nodes;

use App\Models\Node;
use Dedoc\Scramble\Attributes\BodyParameter;

// Attributes are not inherited, so these repeat the parent's against the same descriptions.
#[BodyParameter('name', description: self::FIELDS['name'])]
#[BodyParameter('description', description: self::FIELDS['description'])]
#[BodyParameter('public', description: self::FIELDS['public'])]
#[BodyParameter('fqdn', description: self::FIELDS['fqdn'])]
#[BodyParameter('scheme', description: self::FIELDS['scheme'])]
#[BodyParameter('behind_proxy', description: self::FIELDS['behind_proxy'])]
#[BodyParameter('memory', description: self::FIELDS['memory'])]
#[BodyParameter('memory_overallocate', description: self::FIELDS['memory_overallocate'])]
#[BodyParameter('disk', description: self::FIELDS['disk'])]
#[BodyParameter('disk_overallocate', description: self::FIELDS['disk_overallocate'])]
#[BodyParameter('cpu', description: self::FIELDS['cpu'])]
#[BodyParameter('cpu_overallocate', description: self::FIELDS['cpu_overallocate'])]
#[BodyParameter('daemon_base', description: self::FIELDS['daemon_base'])]
#[BodyParameter('daemon_sftp', description: self::FIELDS['daemon_sftp'])]
#[BodyParameter('daemon_sftp_alias', description: self::FIELDS['daemon_sftp_alias'])]
#[BodyParameter('daemon_listen', description: self::FIELDS['daemon_listen'])]
#[BodyParameter('daemon_connect', description: self::FIELDS['daemon_connect'])]
#[BodyParameter('maintenance_mode', description: self::FIELDS['maintenance_mode'])]
#[BodyParameter('upload_size', description: self::FIELDS['upload_size'])]
#[BodyParameter('tags', description: self::FIELDS['tags'])]
class UpdateNodeRequest extends StoreNodeRequest
{
    /**
     * @param  array<string, string|string[]>|null  $rules
     * @return array<string, string|string[]>
     */
    public function rules(?array $rules = null): array
    {
        // No route to read the node off of when the rules are being inspected rather than applied,
        // which is how the API documentation is generated.
        if (!$this->route()) {
            return parent::rules($rules);
        }

        /** @var Node $node */
        $node = $this->route()->parameter('node');

        return parent::rules(Node::getRulesForUpdate($node));
    }
}
