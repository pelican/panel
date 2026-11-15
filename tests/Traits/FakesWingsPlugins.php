<?php

namespace App\Tests\Traits;

use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

/**
 * Answers shaped like the Wings plugin API (pelican/wings contract v2, section A).
 */
trait FakesWingsPlugins
{
    /**
     * One plugin (A3).
     *
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function wingsPlugin(string $id, array $overrides = [], array $meta = []): array
    {
        return array_replace([
            'id' => $id,
            'name' => $id,
            'kind' => 'external',
            'author' => 'Pelican',
            'version' => '1.0.0',
            'description' => 'A plugin',
            'category' => 'events',
            'capability' => 'events',
            'api_version' => 1,
            'transport' => 'stdio',
            'events' => ['server.state'],
            'host_access' => [],
            'entrypoint' => ['bun', 'index.ts'],
            'settings' => [],
            'meta' => array_replace([
                'status' => 'enabled',
                'load_order' => 0,
                'registered' => ['hooks' => ['server.state']],
                'values' => [],
                'enabled' => true,
                'enable_reason' => 'autoload',
                'trust' => 'trusted',
                'is_trusted' => true,
                'can_enable' => false,
                'can_disable' => true,
                'can_configure' => false,
                'can_update' => false,
                'can_uninstall' => false,
            ], $meta),
        ], $overrides);
    }

    /**
     * GET /api/plugins (A2).
     *
     * @param  array<int, array<string, mixed>>  $plugins
     */
    protected function wingsList(array $plugins, string $level = 'view'): PromiseInterface
    {
        return Http::response([
            'enabled' => true,
            'directory' => '/var/lib/pelican/plugins',
            'panel_access' => $level,
            'data' => $plugins,
        ]);
    }

    /**
     * An error from Wings itself; it carries the node's User-Agent, which the daemon client checks.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function wingsError(Node $node, int $status, string $message, array $extra = []): PromiseInterface
    {
        return Http::response(['error' => $message, ...$extra], $status, $this->wingsHeaders($node));
    }

    /** A 403 for the node's access level (A1). */
    protected function wingsForbidden(Node $node, string $level, string $required): PromiseInterface
    {
        return $this->wingsError($node, 403, "plugins.panel_access is \"$level\" on this node; this request needs \"$required\" or higher.", [
            'panel_access' => $level,
            'required' => $required,
        ]);
    }

    /** What a Wings without the plugin API answers. */
    protected function wingsNotFound(Node $node): PromiseInterface
    {
        return Http::response('404 page not found', 404, $this->wingsHeaders($node));
    }

    /**
     * The 202 of the first step of an install, update or import (A4).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function wingsStaged(string $action, string $name, string $token, array $overrides = []): PromiseInterface
    {
        return Http::response($this->wingsStagedBody($action, $name, $token, $overrides), 202);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function wingsStagedBody(string $action, string $name, string $token, array $overrides = []): array
    {
        $path = $action === 'import' ? '/api/plugins/import' : "/api/plugins/$name/$action";

        return array_replace([
            'status' => 'approval_required',
            'action' => $action,
            'plugin' => [
                'name' => $name,
                'version' => '1.2.0',
                'author' => 'Pelican',
                'description' => 'Logs power state changes.',
                'capability' => 'events',
                'source' => 'https://github.com/example/' . $name,
                'entrypoint' => ['bun', 'index.ts'],
                'events' => ['server.state'],
                'settings' => [],
            ],
            'host_access' => ['server.logs'],
            'replaces' => null,
            'signed' => true,
            'approval_token' => $token,
            'expires_at' => now()->addMinutes(10)->toRfc3339String(),
            'approve_with' => ['method' => 'POST', 'path' => $path, 'body' => ['approve' => $token]],
        ], $overrides);
    }

    /** @return array<string, string> */
    protected function wingsHeaders(Node $node): array
    {
        return ['User-Agent' => "Pelican Wings/vdevelop (id:{$node->daemon_token_id})"];
    }

    /**
     * An admin who may manage plugins, but only on $allowed.
     */
    protected function nodeRestrictedPluginAdmin(Node $allowed): User
    {
        [$user] = generateTestAccount();

        $role = Role::factory()->create(['name' => 'Plugins on ' . $allowed->name, 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::findOrCreate('viewList plugin', 'web'),
            Permission::findOrCreate('view plugin', 'web'),
            Permission::findOrCreate('create plugin', 'web'),
            Permission::findOrCreate('update plugin', 'web'),
            Permission::findOrCreate('delete plugin', 'web'),
        ]);
        $role->nodes()->sync([$allowed->id]);
        $user->syncRoles($role);

        return $user->fresh();
    }

    /** Whether a recorded request went to $node. */
    protected function sentTo(Node $node, Request $request): bool
    {
        return parse_url($request->url(), PHP_URL_HOST) === $node->fqdn;
    }
}
