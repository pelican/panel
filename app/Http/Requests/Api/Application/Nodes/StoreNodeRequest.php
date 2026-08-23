<?php

namespace App\Http\Requests\Api\Application\Nodes;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\Node;
use App\Services\Acl\Api\AdminAcl;
use Dedoc\Scramble\Attributes\BodyParameter;

// The rules come straight off the model, so there is no rules array to hang comments on. These
// describe the fields without touching the inferred types or the validation itself. Attributes
// are not inherited, so UpdateNodeRequest repeats them against the same descriptions.
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
class StoreNodeRequest extends ApplicationApiRequest
{
    /** @var array<string, string> */
    public const FIELDS = [
        'name' => 'Name the node is shown under in the Panel.',
        'description' => 'Free form text describing the node.',
        'public' => 'Whether the node can be picked by automatic deployment.',
        'fqdn' => 'Domain name or IP address the Panel reaches Wings on.',
        'scheme' => 'Protocol the Panel uses to reach Wings. Use `https` unless the node sits behind a proxy that terminates TLS.',
        'behind_proxy' => 'Whether Wings sits behind a reverse proxy that terminates TLS for it.',
        'memory' => 'Memory the node makes available to servers, in MiB.',
        'memory_overallocate' => 'Percentage of memory that may be handed out beyond the limit. Use `-1` for unlimited and `0` to allow none.',
        'disk' => 'Disk space the node makes available to servers, in MiB.',
        'disk_overallocate' => 'Percentage of disk space that may be handed out beyond the limit. Use `-1` for unlimited and `0` to allow none.',
        'cpu' => 'CPU the node makes available to servers, where each 100 is one core.',
        'cpu_overallocate' => 'Percentage of CPU that may be handed out beyond the limit. Use `-1` for unlimited and `0` to allow none.',
        'daemon_base' => 'Absolute path on the node where server files are stored.',
        'daemon_sftp' => 'Port Wings listens on for SFTP connections.',
        'daemon_sftp_alias' => 'Hostname shown to users for SFTP connections when it differs from the node FQDN.',
        'daemon_listen' => 'Port Wings listens on for API requests from the Panel.',
        'daemon_connect' => 'Port the Panel connects to Wings on when it differs from the listen port, such as behind a proxy.',
        'maintenance_mode' => 'Whether the node is in maintenance mode, which stops its servers from being started.',
        'upload_size' => 'Largest file that may be uploaded through the file manager, in MiB.',
        'tags' => 'Tags used to steer automatic deployment towards or away from this node.',
    ];

    protected ?string $resource = Node::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /**
     * @param  array<string, string|string[]>|null  $rules
     * @return array<string, string|string[]>
     */
    public function rules(?array $rules = null): array
    {
        return collect($rules ?? Node::getRules())->mapWithKeys(function ($value, $key) {
            return [snake_case($key) => $value];
        })->toArray();
    }

    /**
     * Fields to rename for clarity in the API response.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'daemon_base' => 'Daemon Base Path',
            'upload_size' => 'File Upload Size Limit',
            'public' => 'Node Visibility',
        ];
    }

    /**
     * Change the formatting of some data keys in the validated response data
     * to match what the application expects in the services.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $response = parent::validated();
        $response['daemon_base'] = $response['daemon_base'] ?? (new Node())->getAttribute('daemon_base');

        return $response;
    }
}
