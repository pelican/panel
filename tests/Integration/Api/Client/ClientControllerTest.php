<?php

namespace App\Tests\Integration\Api\Client;

use App\Enums\RolePermissionModels;
use App\Enums\SubuserPermission;
use App\Models\Allocation;
use App\Models\Role;
use App\Models\Server;
use App\Models\Subuser;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;

class ClientControllerTest extends ClientApiIntegrationTestCase
{
    /**
     * Test that only the servers a logged-in user is assigned to are returned by the
     * API endpoint. Obviously there are cases such as being an administrator or being
     * a subuser, but for this test we just want to test a basic scenario and pretend
     * subusers do not exist at all.
     */
    public function test_only_logged_in_users_servers_are_returned(): void
    {
        /** @var User[] $users */
        $users = User::factory()->times(3)->create();

        /** @var Server[] $servers */
        $servers = [
            $this->createServerModel(['user_id' => $users[0]->id]),
            $this->createServerModel(['user_id' => $users[1]->id]),
            $this->createServerModel(['user_id' => $users[2]->id]),
        ];

        $response = $this->actingAs($users[0])->getJson('/api/client');

        $response->assertOk();
        $response->assertJsonPath('object', 'list');
        $response->assertJsonPath('data.0.object', Server::RESOURCE_NAME);
        $response->assertJsonPath('data.0.attributes.identifier', $servers[0]->uuid_short);
        $response->assertJsonPath('data.0.attributes.server_owner', true);
        $response->assertJsonPath('meta.pagination.total', 1);
        $response->assertJsonPath('meta.pagination.per_page', 50);
    }

    /**
     * Test that using ?filter[*]=name|uuid returns any server matching that name or UUID
     * with the search filters.
     */
    public function test_servers_are_filtered_using_name_and_uuid_information(): void
    {
        /** @var User[] $users */
        $users = User::factory()->times(2)->create();
        $users[0]->syncRoles(Role::getRootAdmin());

        /** @var Server[] $servers */
        $servers = [
            $this->createServerModel(['user_id' => $users[0]->id, 'name' => 'julia']),
            $this->createServerModel(['user_id' => $users[1]->id, 'uuid_short' => '12121212', 'name' => 'janice']),
            $this->createServerModel(['user_id' => $users[1]->id, 'uuid' => '88788878-12356789', 'external_id' => 'ext123', 'name' => 'julia']),
            $this->createServerModel(['user_id' => $users[1]->id, 'uuid' => '88788878-abcdefgh', 'name' => 'jennifer']),
        ];

        $this->actingAs($users[1])->getJson('/api/client?filter[*]=julia')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $servers[2]->uuid_short);

        $this->actingAs($users[1])->getJson('/api/client?filter[*]=ext123')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $servers[2]->uuid_short);

        $this->actingAs($users[1])->getJson('/api/client?filter[*]=ext123')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $servers[2]->uuid_short);

        $this->actingAs($users[1])->getJson('/api/client?filter[*]=12121212')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $servers[1]->uuid_short);

        $this->actingAs($users[1])->getJson('/api/client?filter[*]=88788878')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $servers[2]->uuid_short)
            ->assertJsonPath('data.1.attributes.identifier', $servers[3]->uuid_short);

        $this->actingAs($users[1])->getJson('/api/client?filter[*]=88788878-abcd')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $servers[3]->uuid_short);

        $this->actingAs($users[0])->getJson('/api/client?filter[*]=julia&type=admin-all')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $servers[0]->uuid_short)
            ->assertJsonPath('data.1.attributes.identifier', $servers[2]->uuid_short);
    }

    /**
     * Test that using ?filter[*]=:25565 or ?filter[*]=192.168.1.1:25565 returns only those servers
     * with the same allocation for the given user.
     */
    public function test_servers_are_filtered_using_allocation_information(): void
    {
        /** @var User $user */
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount();
        $server2 = $this->createServerModel(['user_id' => $user->id, 'node_id' => $server->node_id]);

        $allocation = Allocation::factory()->create(['node_id' => $server->node_id, 'server_id' => $server->id, 'ip' => '192.168.1.1', 'port' => 25565]);
        $allocation2 = Allocation::factory()->create(['node_id' => $server->node_id, 'server_id' => $server2->id, 'ip' => '192.168.1.1', 'port' => 25570]);

        $server->update(['allocation_id' => $allocation->id]);
        $server2->update(['allocation_id' => $allocation2->id]);

        $server->refresh();
        $server2->refresh();

        $this->actingAs($user)->getJson('/api/client?filter[*]=192.168.1.1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $server->uuid_short)
            ->assertJsonPath('data.1.attributes.identifier', $server2->uuid_short);

        $this->actingAs($user)->getJson('/api/client?filter[*]=192.168.1.1:25565')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $server->uuid_short);

        $this->actingAs($user)->getJson('/api/client?filter[*]=:25570')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $server2->uuid_short);

        $this->actingAs($user)->getJson('/api/client?filter[*]=:255')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.identifier', $server->uuid_short)
            ->assertJsonPath('data.1.attributes.identifier', $server2->uuid_short);
    }

    /**
     * Test that servers where the user is a subuser are returned by default in the API call.
     */
    public function test_servers_user_is_a_subuser_of_are_returned(): void
    {
        /** @var User[] $users */
        $users = User::factory()->times(3)->create();
        $servers = [
            $this->createServerModel(['user_id' => $users[0]->id]),
            $this->createServerModel(['user_id' => $users[1]->id]),
            $this->createServerModel(['user_id' => $users[2]->id]),
        ];

        // Set user 0 as a subuser of server 1. Thus, we should get two servers
        // back in the response when making the API call as user 0.
        Subuser::query()->create([
            'user_id' => $users[0]->id,
            'server_id' => $servers[1]->id,
            'permissions' => [SubuserPermission::WebsocketConnect->value],
        ]);

        $response = $this->actingAs($users[0])->getJson('/api/client');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.attributes.server_owner', true);
        $response->assertJsonPath('data.0.attributes.identifier', $servers[0]->uuid_short);
        $response->assertJsonPath('data.1.attributes.server_owner', false);
        $response->assertJsonPath('data.1.attributes.identifier', $servers[1]->uuid_short);
    }

    /**
     * Returns only servers that the user owns, not servers they are a subuser of.
     */
    public function test_filter_only_owner_servers(): void
    {
        /** @var User[] $users */
        $users = User::factory()->times(3)->create();
        $servers = [
            $this->createServerModel(['user_id' => $users[0]->id]),
            $this->createServerModel(['user_id' => $users[1]->id]),
            $this->createServerModel(['user_id' => $users[2]->id]),
        ];

        // Set user 0 as a subuser of server 1. Thus, we should get two servers
        // back in the response when making the API call as user 0.
        Subuser::query()->create([
            'user_id' => $users[0]->id,
            'server_id' => $servers[1]->id,
            'permissions' => [SubuserPermission::WebsocketConnect],
        ]);

        $response = $this->actingAs($users[0])->getJson('/api/client?type=owner');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.server_owner', true);
        $response->assertJsonPath('data.0.attributes.identifier', $servers[0]->uuid_short);
    }

    /**
     * Tests that the permissions from the Panel are returned correctly.
     */
    public function test_permissions_are_returned(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/client/permissions')
            ->assertOk()
            ->assertJson([
                'object' => 'system_permissions',
                'attributes' => [
                    'permissions' => Subuser::allPermissionKeys(),
                ],
            ]);
    }

    /**
     * Test that only servers a user can access because they are an administrator are returned. This
     * will always exclude any servers they can see because they're the owner or a subuser of the server.
     */
    public function test_only_admin_level_servers_are_returned(): void
    {
        /** @var User[] $users */
        $users = User::factory()->times(4)->create();
        $users[0]->syncRoles(Role::getRootAdmin());

        $servers = [
            $this->createServerModel(['user_id' => $users[0]->id]),
            $this->createServerModel(['user_id' => $users[1]->id]),
            $this->createServerModel(['user_id' => $users[2]->id]),
            $this->createServerModel(['user_id' => $users[3]->id]),
        ];

        Subuser::query()->create([
            'user_id' => $users[0]->id,
            'server_id' => $servers[1]->id,
            'permissions' => [SubuserPermission::WebsocketConnect->value],
        ]);

        // Only servers 2 & 3 (0 indexed) should be returned by the API at this point. The user making
        // the request is the owner of server 0, and a subuser of server 1, so they should be excluded.
        $response = $this->actingAs($users[0])->getJson('/api/client?type=admin');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $response->assertJsonPath('data.0.attributes.server_owner', false);
        $response->assertJsonPath('data.0.attributes.identifier', $servers[2]->uuid_short);
        $response->assertJsonPath('data.1.attributes.server_owner', false);
        $response->assertJsonPath('data.1.attributes.identifier', $servers[3]->uuid_short);
    }

    /**
     * Test that all servers a user can access as an admin are returned if using ?filter=admin-all.
     */
    public function test_all_servers_are_returned_to_admin(): void
    {
        /** @var User[] $users */
        $users = User::factory()->times(4)->create();
        $users[0]->syncRoles(Role::getRootAdmin());

        $servers = [
            $this->createServerModel(['user_id' => $users[0]->id]),
            $this->createServerModel(['user_id' => $users[1]->id]),
            $this->createServerModel(['user_id' => $users[2]->id]),
            $this->createServerModel(['user_id' => $users[3]->id]),
        ];

        Subuser::query()->create([
            'user_id' => $users[0]->id,
            'server_id' => $servers[1]->id,
            'permissions' => [SubuserPermission::WebsocketConnect->value],
        ]);

        // All servers should be returned.
        $response = $this->actingAs($users[0])->getJson('/api/client?type=admin-all');

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
    }

    /**
     * Test that no servers get returned if the user requests all admin level servers by using
     * ?type=admin or ?type=admin-all in the request.
     */
    #[DataProvider('filterTypeDataProvider')]
    public function test_no_servers_are_returned_if_admin_filter_is_passed_by_regular_user(string $type): void
    {
        /** @var User[] $users */
        $users = User::factory()->times(3)->create();

        $this->createServerModel(['user_id' => $users[0]->id]);
        $this->createServerModel(['user_id' => $users[1]->id]);
        $this->createServerModel(['user_id' => $users[2]->id]);

        $response = $this->actingAs($users[0])->getJson('/api/client?type=' . $type);

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    /**
     * Test that a subuser without the allocation.read permission is only able to see the primary
     * allocation for the server.
     */
    public function test_only_primary_allocation_is_returned_to_subuser(): void
    {
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount([SubuserPermission::WebsocketConnect]);
        $server->allocation->notes = 'Test notes';
        $server->allocation->save();

        Allocation::factory()->times(2)->create([
            'node_id' => $server->node_id,
            'server_id' => $server->id,
        ]);

        $server->refresh();
        $response = $this->actingAs($user)->getJson('/api/client');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.server_owner', false);
        $response->assertJsonPath('data.0.attributes.uuid', $server->uuid);
        $response->assertJsonCount(1, 'data.0.attributes.relationships.allocations.data');
        $response->assertJsonPath('data.0.attributes.relationships.allocations.data.0.attributes.id', $server->allocation->id);
        $response->assertJsonPath('data.0.attributes.relationships.allocations.data.0.attributes.notes', null);
    }

    /**
     * Test that a subuser without the allocation.read permission can still view a server
     * that has no primary allocation assigned.
     */
    public function test_subuser_can_view_server_without_allocation(): void
    {
        /** @var Server $server */
        [$user, $server] = $this->generateTestAccount([SubuserPermission::WebsocketConnect]);
        $server->allocation->update(['server_id' => null]);
        $server->update(['allocation_id' => null]);

        $server->refresh();

        $response = $this->actingAs($user)->getJson('/api/client');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.attributes.uuid', $server->uuid);
        $response->assertJsonCount(0, 'data.0.attributes.relationships.allocations.data');

        $response = $this->actingAs($user)->getJson("/api/client/servers/$server->uuid_short");

        $response->assertOk();
        $response->assertJsonPath('attributes.uuid', $server->uuid);
        $response->assertJsonCount(0, 'attributes.relationships.allocations.data');
    }

    /**
     * Test that a non-root admin with the "update server" role permission receives the
     * permission gated includes (variables, allocations) when viewing a server they do not own.
     */
    public function test_admin_with_update_permission_can_view_server_variables(): void
    {
        /** @var Server $server */
        [, $server] = $this->generateTestAccount();
        Allocation::factory()->create([
            'node_id' => $server->node_id,
            'server_id' => $server->id,
        ]);

        $role = Role::factory()->create(['name' => 'Server Manager', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::findOrCreate(RolePermissionModels::Server->viewAny(), 'web'),
            Permission::findOrCreate(RolePermissionModels::Server->view(), 'web'),
            Permission::findOrCreate(RolePermissionModels::Server->update(), 'web'),
        ]);

        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->syncRoles($role);

        $response = $this->actingAs($admin)->getJson("/api/client/servers/$server->uuid_short");

        $response->assertOk();
        $response->assertJsonPath('attributes.server_owner', false);
        $response->assertJsonPath('attributes.relationships.variables.object', 'list');
        $response->assertJsonCount(2, 'attributes.relationships.allocations.data');
    }

    public static function filterTypeDataProvider(): array
    {
        return [['admin'], ['admin-all']];
    }
}
