<?php

namespace App\Tests\Unit\Models;

use App\Models\Node;
use App\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

class NodeDaemonConfigTest extends TestCase
{
    private function makeNode(?array $daemonConfig): Node
    {
        $node = new Node([
            'fqdn' => 'node.example.com',
            'scheme' => 'https',
            'upload_size' => 100,
            'daemon_config' => $daemonConfig,
        ]);
        $node->uuid = 'uuid';
        $node->daemon_token_id = 'token-id';
        $node->daemon_token = 'token';
        $node->setRelation('mounts', new Collection());

        return $node;
    }

    public function test_unset_keys_are_not_sent_to_wings(): void
    {
        $config = $this->makeNode(null)->getConfiguration();

        $this->assertFalse($config['debug']);
        $this->assertArrayNotHasKey('docker', $config);
        $this->assertArrayNotHasKey('throttles', $config);
        $this->assertSame(['data', 'sftp'], array_keys($config['system']));
    }

    public function test_values_are_cast_to_the_types_wings_expects(): void
    {
        $config = $this->makeNode([
            'debug' => '1',
            'docker' => [
                'container_pid_limit' => '1024',
                'overhead' => ['default_multiplier' => '1.1'],
                'network' => ['dns' => ['8.8.8.8', '', null], 'IPv6' => '0'],
            ],
            'throttles' => ['enabled' => '0', 'lines' => ''],
        ])->getConfiguration();

        $this->assertTrue($config['debug']);
        $this->assertSame(1024, $config['docker']['container_pid_limit']);
        $this->assertSame(1.1, $config['docker']['overhead']['default_multiplier']);
        $this->assertSame(['8.8.8.8'], $config['docker']['network']['dns']);
        $this->assertFalse($config['docker']['network']['IPv6']);
        $this->assertSame(['enabled' => false], $config['throttles']);
    }

    public function test_nested_overrides_merge_with_panel_managed_keys(): void
    {
        $config = $this->makeNode([
            'api' => ['trusted_proxies' => ['10.0.0.1']],
            'system' => ['timezone' => 'Europe/Berlin', 'sftp' => ['bind_address' => '10.0.0.2']],
        ])->getConfiguration();

        $this->assertSame(['10.0.0.1'], $config['api']['trusted_proxies']);
        $this->assertSame(100, $config['api']['upload_limit']);
        $this->assertSame('Europe/Berlin', $config['system']['timezone']);
        $this->assertSame(['bind_address' => '10.0.0.2', 'bind_port' => 2022], $config['system']['sftp']);
    }

    public function test_ssl_paths_default_to_lets_encrypt(): void
    {
        $ssl = $this->makeNode(null)->getConfiguration()['api']['ssl'];

        $this->assertTrue($ssl['enabled']);
        $this->assertSame('/etc/letsencrypt/live/node.example.com/fullchain.pem', $ssl['cert']);
        $this->assertSame('/etc/letsencrypt/live/node.example.com/privkey.pem', $ssl['key']);
    }

    public function test_custom_ssl_paths_replace_the_defaults(): void
    {
        $ssl = $this->makeNode([
            'api' => ['ssl' => ['cert' => '/etc/ssl/wings.crt', 'key' => '/etc/ssl/wings.key', 'enabled' => false]],
        ])->getConfiguration()['api']['ssl'];

        $this->assertSame(['enabled' => true, 'cert' => '/etc/ssl/wings.crt', 'key' => '/etc/ssl/wings.key'], $ssl);
    }

    public function test_overrides_cannot_replace_panel_managed_or_unknown_keys(): void
    {
        $config = $this->makeNode([
            'token' => 'stolen',
            'ignore_panel_config_updates' => true,
            'api' => ['port' => 1],
        ])->getConfiguration();

        $this->assertSame('token', $config['token']);
        $this->assertSame(8080, $config['api']['port']);
        $this->assertArrayNotHasKey('ignore_panel_config_updates', $config);
    }

    public function test_normalize_drops_empty_and_unknown_keys(): void
    {
        $this->assertNull(Node::normalizeDaemonConfig(null));
        $this->assertNull(Node::normalizeDaemonConfig([
            'debug' => null,
            'docker' => ['container_pid_limit' => '', 'network' => ['dns' => []]],
            'unknown' => 'value',
        ]));
        $this->assertSame(
            ['docker' => ['container_pid_limit' => '512']],
            Node::normalizeDaemonConfig(['docker' => ['container_pid_limit' => '512', 'tmpfs_size' => null]]),
        );
    }

    public function test_yaml_renders_nested_overrides_as_blocks(): void
    {
        $yaml = $this->makeNode([
            'docker' => ['network' => ['interfaces' => ['v4' => ['subnet' => '172.20.0.0/16']]]],
        ])->getYamlConfiguration();

        $this->assertStringContainsString("      v4:\n        subnet: 172.20.0.0/16", $yaml);
    }
}
