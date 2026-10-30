<?php

namespace App\Tests\Integration\Api;

use App\Enums\RolePermissionModels;
use App\Enums\SubuserPermission;
use App\Models\ApiKey;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Egg;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\Subuser;
use App\Models\User;
use App\Models\UserSSHKey;
use App\Services\Acl\Api\AdminAcl;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Checks what data each kind of caller gets back once they are let through, as opposed
 * to the matrix tests which only check whether they are let through at all.
 */
class PermissionVisibilityTest extends IntegrationTestCase
{
    private function subuser(Server $server, array $permissions): User
    {
        $user = User::factory()->create();
        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'permissions' => array_map(fn ($p) => $p instanceof SubuserPermission ? $p->value : $p, $permissions),
        ]);

        return $user;
    }

    private function admin(array $permissions, ?Node $restrictTo = null): User
    {
        $role = Role::factory()->create(['name' => 'Admin ' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(array_map(fn ($p) => Permission::findOrCreate($p, 'web'), $permissions));
        if ($restrictTo) {
            $role->nodes()->sync([$restrictTo->id]);
        }

        $user = User::factory()->create();
        $user->syncRoles($role);

        return $user;
    }

    private function useKey(User $user, int $type, array $permissions = []): void
    {
        $key = ApiKey::factory()->create(['user_id' => $user->id, 'key_type' => $type, 'permissions' => $permissions]);
        // The guard caches the authenticated user (and their token) between requests.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Accept', 'application/vnd.panel.v1+json')
            ->withHeader('Authorization', 'Bearer ' . $key->identifier . $key->token);
    }

    public function test_server_variables_and_startup_hidden_without_startup_read(): void
    {
        $server = $this->createServerModel();

        $this->actingAs($this->subuser($server, [SubuserPermission::WebsocketConnect]))
            ->getJson("/api/client/servers/$server->uuid?include=variables")
            ->assertOk()
            ->assertJsonPath('attributes.relationships.variables.object', 'null_resource');

        $this->actingAs($this->subuser($server, [SubuserPermission::StartupRead]))
            ->getJson("/api/client/servers/$server->uuid?include=variables")
            ->assertOk()
            ->assertJsonPath('attributes.relationships.variables.object', 'list');
    }

    public function test_server_subusers_hidden_without_user_read(): void
    {
        $server = $this->createServerModel();

        $this->actingAs($this->subuser($server, [SubuserPermission::WebsocketConnect]))
            ->getJson("/api/client/servers/$server->uuid?include=subusers")
            ->assertOk()
            ->assertJsonPath('attributes.relationships.subusers.object', 'null_resource');

        $this->actingAs($this->subuser($server, [SubuserPermission::UserRead]))
            ->getJson("/api/client/servers/$server->uuid?include=subusers")
            ->assertOk()
            ->assertJsonPath('attributes.relationships.subusers.object', 'list');
    }

    public function test_database_password_requires_view_password_permission(): void
    {
        $server = $this->createServerModel(['database_limit' => 5]);
        $host = DatabaseHost::factory()->create(['host' => '127.0.0.1', 'port' => 1]);
        Database::factory()->create(['server_id' => $server->id, 'database_host_id' => $host->id]);

        $this->actingAs($this->subuser($server, [SubuserPermission::DatabaseRead]))
            ->getJson("/api/client/servers/$server->uuid/databases?include=password")
            ->assertOk()
            ->assertJsonPath('data.0.attributes.relationships.password.object', 'null_resource');

        $this->actingAs($this->subuser($server, [SubuserPermission::DatabaseRead, SubuserPermission::DatabaseViewPassword]))
            ->getJson("/api/client/servers/$server->uuid/databases?include=password")
            ->assertOk()
            ->assertJsonPath('data.0.attributes.relationships.password.object', 'database_password');
    }

    public function test_client_server_list_only_shows_accessible_servers(): void
    {
        $owned = $this->createServerModel();
        $shared = $this->createServerModel();
        $this->createServerModel();

        $user = $owned->user;
        Subuser::query()->create(['user_id' => $user->id, 'server_id' => $shared->id, 'permissions' => ['websocket.connect']]);

        $response = $this->actingAs($user)->getJson('/api/client')->assertOk();
        $ids = collect($response->json('data'))->pluck('attributes.uuid')->sort()->values()->all();
        $this->assertSame(collect([$owned->uuid, $shared->uuid])->sort()->values()->all(), $ids);

        $this->actingAs($user)->getJson('/api/client?type=owner')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson('/api/client?type=admin-all')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_node_restricted_admin_cannot_view_server_on_other_node(): void
    {
        $server = $this->createServerModel();
        $admin = $this->admin([
            RolePermissionModels::Server->viewAny(),
            RolePermissionModels::Server->view(),
            RolePermissionModels::Server->update(),
        ], Node::factory()->create());

        $this->actingAs($admin)->getJson("/api/client/servers/$server->uuid")->assertNotFound();
    }

    public function test_view_server_admin_can_view_but_not_change_server(): void
    {
        $server = $this->createServerModel();
        $admin = $this->admin([RolePermissionModels::Server->viewAny(), RolePermissionModels::Server->view()]);

        $this->actingAs($admin)->getJson("/api/client/servers/$server->uuid")
            ->assertOk()
            ->assertJsonPath('attributes.server_owner', false);

        $this->actingAs($admin)->postJson("/api/client/servers/$server->uuid/settings/rename", ['name' => 'Nope'])->assertForbidden();
        $this->assertNotSame('Nope', $server->fresh()->name);
    }

    public function test_admin_server_list_respects_view_permission_and_node_restrictions(): void
    {
        $allowedNode = Node::factory()->create();
        $visible = $this->createServerModel(['node_id' => $allowedNode->id]);
        $this->createServerModel();

        $admin = $this->admin([RolePermissionModels::Server->viewAny(), RolePermissionModels::Server->view()], $allowedNode);

        $this->actingAs($admin)->getJson('/api/client?type=admin-all')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.uuid', $visible->uuid);

        $regular = User::factory()->create();
        $this->actingAs($regular)->getJson('/api/client?type=admin-all')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_admin_with_update_server_can_read_server_activity(): void
    {
        $server = $this->createServerModel();
        $admin = $this->admin([
            RolePermissionModels::Server->viewAny(),
            RolePermissionModels::Server->view(),
            RolePermissionModels::Server->update(),
        ]);

        $this->actingAs($admin)->getJson("/api/client/servers/$server->uuid/activity")->assertOk();
    }

    public function test_users_cannot_touch_other_users_keys(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $key = ApiKey::factory()->create(['user_id' => $owner->id, 'key_type' => ApiKey::TYPE_ACCOUNT]);
        $ssh = UserSSHKey::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)->getJson('/api/client/account/api-keys')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($other)->getJson('/api/client/account/ssh-keys')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($other)->deleteJson("/api/client/account/api-keys/$key->identifier");
        $this->actingAs($other)->deleteJson("/api/client/account/ssh-keys/$ssh->fingerprint");

        $this->assertDatabaseHas('api_keys', ['id' => $key->id]);
        $this->assertNotSoftDeleted('user_ssh_keys', ['id' => $ssh->id]);
    }

    public function test_application_includes_respect_key_permissions(): void
    {
        $server = $this->createServerModel();
        $root = User::factory()->create();
        $root->syncRoles(Role::getRootAdmin());

        $this->useKey($root, ApiKey::TYPE_APPLICATION, [Server::RESOURCE_NAME => AdminAcl::READ]);

        $response = $this->getJson("/api/application/servers/$server->id?include=user,node,allocations,egg,databases")->assertOk();

        foreach (['user', 'node', 'allocations', 'egg', 'databases'] as $include) {
            $object = $response->json("attributes.relationships.$include.object");
            $this->assertContains($object, [null, 'null_resource'], "include $include should be hidden from a server-only key, got $object");
        }
    }

    public function test_mount_relations_need_read_on_mount_and_related_resource(): void
    {
        $server = $this->createServerModel();
        $mount = Mount::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => Str::random(8),
            'source' => '/mnt/' . Str::random(8),
            'target' => '/srv/' . Str::random(8),
        ]);
        $mount->eggs()->attach($server->egg_id);

        $root = User::factory()->create();
        $root->syncRoles(Role::getRootAdmin());

        $this->useKey($root, ApiKey::TYPE_APPLICATION, [Mount::RESOURCE_NAME => AdminAcl::READ]);
        $this->getJson("/api/application/mounts/$mount->id/eggs")->assertForbidden();

        $this->useKey($root, ApiKey::TYPE_APPLICATION, [Egg::RESOURCE_NAME => AdminAcl::READ]);
        $this->getJson("/api/application/mounts/$mount->id/eggs")->assertForbidden();

        $this->useKey($root, ApiKey::TYPE_APPLICATION, [Mount::RESOURCE_NAME => AdminAcl::READ, Egg::RESOURCE_NAME => AdminAcl::READ]);
        $this->getJson("/api/application/mounts/$mount->id/eggs")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_non_root_admin_cannot_change_root_admin_via_application_api(): void
    {
        $root = User::factory()->create();
        $root->syncRoles(Role::getRootAdmin());

        $admin = $this->admin([RolePermissionModels::User->viewAny(), RolePermissionModels::User->update()]);
        $this->useKey($admin, ApiKey::TYPE_APPLICATION, [User::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE]);

        $this->patchJson("/api/application/users/$root->id", [
            'email' => 'hijacked@example.com',
            'username' => $root->username,
        ])->assertForbidden();

        $this->assertNotSame('hijacked@example.com', $root->fresh()->email, 'non-root admin changed the root admin\'s email through the application API');
    }

    public function test_root_admin_can_still_edit_other_root_admins(): void
    {
        $target = User::factory()->create();
        $target->syncRoles(Role::getRootAdmin());

        $root = User::factory()->create();
        $root->syncRoles(Role::getRootAdmin());
        $this->useKey($root, ApiKey::TYPE_APPLICATION, [User::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE]);

        $this->patchJson("/api/application/users/$target->id", [
            'email' => 'updated@example.com',
            'username' => $target->username,
        ])->assertOk();

        $this->assertSame('updated@example.com', $target->fresh()->email);
    }
}
