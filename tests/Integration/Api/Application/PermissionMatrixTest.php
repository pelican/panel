<?php

namespace App\Tests\Integration\Api\Application;

use App\Enums\RolePermissionModels;
use App\Models\Allocation;
use App\Models\ApiKey;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Egg;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Models\WebhookConfiguration;
use App\Services\Acl\Api\AdminAcl;
use App\Tests\Integration\IntegrationTestCase;
use App\Tests\Traits\Integration\CreatesTestModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;

/**
 * Runs every application API endpoint against every kind of caller and checks the
 * caller is let through or refused as expected.
 *
 * "allow" only means authorization passed: write endpoints are mostly sent empty bodies so
 * they stop at validation (422) and never touch anything outside the test database.
 */
class PermissionMatrixTest extends IntegrationTestCase
{
    use CreatesTestModels;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response([], 200)]);
    }

    #[DataProvider('endpointProvider')]
    public function test_endpoint_authorization(string $method, string $path, string $resource, int $level, ?string $roleModel, array $body): void
    {
        $mismatches = [];

        foreach (self::ACTORS as $actor) {
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
            $this->buildFixtures();

            $expected = $this->expectationFor($actor, $level, $roleModel);
            $this->authenticateAs($actor, $path, $resource, $roleModel);

            $response = $this->json($method, '/api/application' . strtr($path, $this->ids), $body);
            $status = $response->getStatusCode();

            $this->record($actor, $method, $path, $resource, $level, $expected, $status);

            $ok = match ($expected) {
                'allow' => !in_array($status, [401, 403, 404]),
                'forbid' => in_array($status, [401, 403]),
            };

            if (!$ok) {
                $mismatches[] = "$actor: expected $expected, got $status";
            }
        }

        $this->assertEmpty($mismatches, "$method $path
" . implode('
', $mismatches));
    }

    private const ACTORS = [
        'root_full_key',
        'root_read_only_key',
        'root_no_access_key',
        'demoted_user_full_key',
        'root_account_key',
        'regular_user_account_key',
        'session_root',
        'session_admin_with_role_permissions',
        'session_admin_without_role_permissions',
        'limited_admin_full_key',
    ];

    public static function endpointProvider(): array
    {
        $cases = [];
        foreach (self::endpoints() as [$method, $path, $resource, $level, $roleModel, $body]) {
            $cases["$method $path"] = [$method, $path, $resource, $level, $roleModel?->value, $body];
        }

        return $cases;
    }

    /**
     * @return array<array{string, string, string, int, ?RolePermissionModels, array}>
     */
    private static function endpoints(): array
    {
        $r = AdminAcl::READ;
        $w = AdminAcl::WRITE;
        $user = [User::RESOURCE_NAME, RolePermissionModels::User];
        $node = [Node::RESOURCE_NAME, RolePermissionModels::Node];
        $alloc = [Allocation::RESOURCE_NAME, RolePermissionModels::Allocation];
        $server = [Server::RESOURCE_NAME, RolePermissionModels::Server];
        $db = [Database::RESOURCE_NAME, RolePermissionModels::Database];
        $egg = [Egg::RESOURCE_NAME, RolePermissionModels::Egg];
        $dbHost = [DatabaseHost::RESOURCE_NAME, RolePermissionModels::DatabaseHost];
        $mount = [Mount::RESOURCE_NAME, RolePermissionModels::Mount];
        $role = [Role::RESOURCE_NAME, RolePermissionModels::Role];
        $plugin = [Plugin::RESOURCE_NAME, null];
        $webhook = [WebhookConfiguration::RESOURCE_NAME, RolePermissionModels::Webhook];

        $e = fn (string $method, string $path, array $res, int $level, array $body = []) => [$method, $path, $res[0], $level, $res[1], $body];

        return [
            $e('GET', '/users', $user, $r),
            $e('GET', '/users/{user}', $user, $r),
            $e('GET', '/users/external/{user_ext}', $user, $r),
            $e('POST', '/users', $user, $w),
            $e('PATCH', '/users/{user}', $user, $w),
            $e('PATCH', '/users/{user}/roles/assign', $user, $w),
            $e('PATCH', '/users/{user}/roles/remove', $user, $w),
            $e('DELETE', '/users/{user}', $user, $w),

            $e('GET', '/nodes', $node, $r),
            $e('GET', '/nodes/deployable', $node, $r),
            $e('GET', '/nodes/{node}', $node, $r),
            $e('GET', '/nodes/{node}/configuration', $node, $r),
            $e('POST', '/nodes', $node, $w),
            $e('PATCH', '/nodes/{node}', $node, $w),
            $e('DELETE', '/nodes/{empty_node}', $node, $w),

            $e('GET', '/nodes/{node}/allocations', $alloc, $r),
            $e('POST', '/nodes/{node}/allocations', $alloc, $w),
            $e('DELETE', '/nodes/{node}/allocations/{allocation}', $alloc, $w),

            $e('GET', '/servers', $server, $r),
            $e('GET', '/servers/{server}', $server, $r),
            $e('GET', '/servers/external/{server_ext}', $server, $r),
            $e('PATCH', '/servers/{server}/details', $server, $w),
            $e('PATCH', '/servers/{server}/build', $server, $w),
            $e('PATCH', '/servers/{server}/startup', $server, $w),
            $e('POST', '/servers', $server, $w),
            $e('POST', '/servers/{server}/suspend', $server, $w),
            $e('POST', '/servers/{server}/unsuspend', $server, $w),
            $e('POST', '/servers/{server}/reinstall', $server, $w),
            $e('POST', '/servers/{server}/transfer', $server, $w),
            $e('POST', '/servers/{server}/transfer/cancel', $server, $w),
            $e('DELETE', '/servers/{server}', $server, $w),
            $e('DELETE', '/servers/{server}/force', $server, $w),

            $e('GET', '/servers/{server}/databases', $db, $r),
            $e('GET', '/servers/{server}/databases/{database}', $db, $r),
            $e('POST', '/servers/{server}/databases', $db, $w),
            $e('POST', '/servers/{server}/databases/{database}/reset-password', $db, $w),
            $e('DELETE', '/servers/{server}/databases/{database}', $db, $w),

            $e('GET', '/eggs', $egg, $r),
            $e('GET', '/eggs/{egg}', $egg, $r),
            $e('GET', '/eggs/{egg}/export', $egg, $r),
            $e('POST', '/eggs/import', $egg, $w),
            $e('DELETE', '/eggs/{unused_egg}', $egg, $w),
            $e('DELETE', '/eggs/uuid/{unused_egg_uuid}', $egg, $w),

            $e('GET', '/database-hosts', $dbHost, $r),
            $e('GET', '/database-hosts/{database_host}', $dbHost, $r),
            $e('POST', '/database-hosts', $dbHost, $w),
            $e('PATCH', '/database-hosts/{database_host}', $dbHost, $w),
            $e('DELETE', '/database-hosts/{empty_database_host}', $dbHost, $w),

            $e('GET', '/mounts', $mount, $r),
            $e('GET', '/mounts/{mount}', $mount, $r),
            $e('GET', '/mounts/{mount}/eggs', $mount, $r),
            $e('GET', '/mounts/{mount}/nodes', $mount, $r),
            $e('GET', '/mounts/{mount}/servers', $mount, $r),
            $e('POST', '/mounts', $mount, $w),
            $e('POST', '/mounts/{mount}/eggs', $mount, $w),
            $e('POST', '/mounts/{mount}/nodes', $mount, $w),
            $e('POST', '/mounts/{mount}/servers', $mount, $w),
            $e('PATCH', '/mounts/{mount}', $mount, $w),
            $e('DELETE', '/mounts/{mount}', $mount, $w),
            $e('DELETE', '/mounts/{mount}/eggs/{egg}', $mount, $w),
            $e('DELETE', '/mounts/{mount}/nodes/{node}', $mount, $w),
            $e('DELETE', '/mounts/{mount}/servers/{server}', $mount, $w),

            $e('GET', '/roles', $role, $r),
            $e('GET', '/roles/{role}', $role, $r),
            $e('POST', '/roles', $role, $w),
            $e('PATCH', '/roles/{role}', $role, $w),
            $e('DELETE', '/roles/{role}', $role, $w),

            $e('GET', '/plugins', $plugin, $r),
            $e('POST', '/plugins/import/file', $plugin, $w),
            $e('POST', '/plugins/import/url', $plugin, $w),
            $e('PUT', '/plugins/hub', $plugin, $w),

            $e('GET', '/webhooks', $webhook, $r),
            $e('GET', '/webhooks/types', $webhook, $r),
            $e('GET', '/webhooks/events', $webhook, $r),
            $e('GET', '/webhooks/{webhook}', $webhook, $r),
            $e('POST', '/webhooks', $webhook, $w),
            $e('POST', '/webhooks/{webhook}/test', $webhook, $w),
            $e('PATCH', '/webhooks/{webhook}', $webhook, $w),
            $e('DELETE', '/webhooks/{webhook}', $webhook, $w),
        ];
    }

    private function expectationFor(string $actor, int $level, ?string $roleModel): string
    {
        return match ($actor) {
            'root_full_key', 'root_account_key', 'session_root' => 'allow',
            'root_read_only_key' => $level === AdminAcl::READ ? 'allow' : 'forbid',
            'root_no_access_key', 'demoted_user_full_key', 'regular_user_account_key', 'session_admin_without_role_permissions' => 'forbid',
            // A role holding every permission for the matching model should be able to use the
            // endpoint from a panel session.
            'session_admin_with_role_permissions' => 'allow',
            // Application keys follow their own permissions, not their owner's role.
            'limited_admin_full_key' => 'allow',
        };
    }

    private function buildFixtures(): void
    {
        $server = $this->createServerModel(['external_id' => 'srv-' . Str::random(6)]);
        $user = User::factory()->create(['external_id' => 'usr-' . Str::random(6)]);
        $emptyNode = Node::factory()->create();
        $allocation = Allocation::factory()->create(['node_id' => $server->node_id]);

        $dbHost = DatabaseHost::factory()->create(['host' => '127.0.0.1', 'port' => 1]);
        $dbHost->nodes()->sync([$server->node_id]);
        $emptyDbHost = DatabaseHost::factory()->create(['host' => '127.0.0.1', 'port' => 1]);
        $database = Database::factory()->create(['server_id' => $server->id, 'database_host_id' => $dbHost->id]);

        $unusedEgg = $this->cloneEggAndVariables($server->egg);

        $mount = Mount::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => Str::random(8),
            'source' => '/mnt/' . Str::random(8),
            'target' => '/srv/' . Str::random(8),
        ]);
        $mount->eggs()->attach($server->egg_id);
        $mount->nodes()->attach($server->node_id);
        $mount->servers()->attach($server->id);

        $role = Role::factory()->create(['name' => 'Fixture Role ' . Str::random(6), 'guard_name' => 'web']);
        $webhook = WebhookConfiguration::factory()->create();

        $this->ids = [
            '{server}' => $server->id,
            '{server_ext}' => $server->external_id,
            '{user}' => $user->id,
            '{user_ext}' => $user->external_id,
            '{node}' => $server->node_id,
            '{empty_node}' => $emptyNode->id,
            '{allocation}' => $allocation->id,
            '{database}' => $database->id,
            '{database_host}' => $dbHost->id,
            '{empty_database_host}' => $emptyDbHost->id,
            '{egg}' => $server->egg_id,
            '{unused_egg}' => $unusedEgg->id,
            '{unused_egg_uuid}' => $unusedEgg->uuid,
            '{mount}' => $mount->id,
            '{role}' => $role->id,
            '{webhook}' => $webhook->id,
        ];
    }

    private function authenticateAs(string $actor, string $path, string $resource, ?string $roleModel): void
    {
        $root = fn () => tap(User::factory()->create(), fn (User $u) => $u->syncRoles(Role::getRootAdmin()));

        switch ($actor) {
            case 'root_full_key':
                $this->useKey($root(), ApiKey::TYPE_APPLICATION, $this->allPermissions());
                break;
            case 'root_read_only_key':
                $this->useKey($root(), ApiKey::TYPE_APPLICATION, array_merge($this->allPermissions(), [$resource => AdminAcl::READ]));
                break;
            case 'root_no_access_key':
                $this->useKey($root(), ApiKey::TYPE_APPLICATION, array_merge($this->allPermissions(), [$resource => AdminAcl::NONE]));
                break;
            case 'demoted_user_full_key':
                // The key was made while the user was an admin; they have since lost every role.
                $this->useKey(User::factory()->create(), ApiKey::TYPE_APPLICATION, $this->allPermissions());
                break;
            case 'root_account_key':
                $this->useKey($root(), ApiKey::TYPE_ACCOUNT, []);
                break;
            case 'regular_user_account_key':
                $this->useKey(User::factory()->create(), ApiKey::TYPE_ACCOUNT, []);
                break;
            case 'session_root':
                $this->actingAs($root(), 'web');
                break;
            case 'session_admin_with_role_permissions':
                // Plugins have no RolePermissionModels case, only special role permissions.
                $permissions = $roleModel
                    ? array_map(fn ($a) => RolePermissionModels::from($roleModel)->{$a}(), ['viewAny', 'view', 'create', 'update', 'delete'])
                    : array_map(fn ($prefix) => "$prefix plugin", Role::SPECIAL_PERMISSIONS['plugin']);
                // Listing what a mount is attached to also needs read on the related model.
                if (preg_match('#^/mounts/\{mount\}/(eggs|nodes|servers)$#', $path, $match)) {
                    $related = RolePermissionModels::from(rtrim($match[1], 's'));
                    array_push($permissions, $related->viewAny(), $related->view());
                }
                $this->actingAs($this->makeAdmin($permissions), 'web');
                break;
            case 'session_admin_without_role_permissions':
                // Admin by virtue of an unrelated permission, so they pass the admin middleware.
                $other = $roleModel === RolePermissionModels::Egg->value ? RolePermissionModels::Node : RolePermissionModels::Egg;
                $this->actingAs($this->makeAdmin([$other->viewAny()]), 'web');
                break;
            case 'limited_admin_full_key':
                $this->useKey($this->makeAdmin([RolePermissionModels::Egg->viewAny()]), ApiKey::TYPE_APPLICATION, $this->allPermissions());
                break;
        }
    }

    private function allPermissions(): array
    {
        return array_fill_keys(ApiKey::getPermissionList(), AdminAcl::READ | AdminAcl::WRITE);
    }

    private function useKey(User $user, int $type, array $permissions): void
    {
        $key = ApiKey::factory()->create([
            'user_id' => $user->id,
            'key_type' => $type,
            'permissions' => $permissions,
        ]);

        $this->withHeader('Accept', 'application/vnd.panel.v1+json')
            ->withHeader('Authorization', 'Bearer ' . $key->identifier . $key->token);
    }

    /**
     * @param  string[]  $permissions
     */
    private function makeAdmin(array $permissions): User
    {
        $role = Role::factory()->create(['name' => 'Admin ' . Str::random(8), 'guard_name' => 'web']);
        $role->givePermissionTo(array_map(fn ($p) => Permission::findOrCreate($p, 'web'), $permissions));

        $user = User::factory()->create();
        $user->syncRoles($role);

        return $user;
    }

    private function record(string $actor, string $method, string $path, string $resource, int $level, string $expected, int $status): void
    {
        if (!$file = getenv('PERMISSION_AUDIT_LOG')) {
            return;
        }

        file_put_contents($file, json_encode([
            'api' => 'application',
            'actor' => $actor,
            'method' => $method,
            'path' => $path,
            'resource' => $resource,
            'level' => $level === AdminAcl::READ ? 'read' : 'write',
            'expected' => $expected,
            'status' => $status,
        ]) . PHP_EOL, FILE_APPEND);
    }
}
