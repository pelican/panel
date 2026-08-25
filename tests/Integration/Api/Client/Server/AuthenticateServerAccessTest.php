<?php

namespace App\Tests\Integration\Api\Client\Server;

use App\Enums\ServerState;
use App\Enums\SubuserPermission;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Permission;

class AuthenticateServerAccessTest extends ClientApiIntegrationTestCase
{
    /**
     * Test that the owner of a suspended server cannot access its files.
     */
    public function test_owner_cannot_access_files_on_suspended_server(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $server->update(['status' => ServerState::Suspended]);

        $this->actingAs($user)
            ->getJson("/api/client/servers/$server->uuid/files/list")
            ->assertStatus(Response::HTTP_CONFLICT);
    }

    /**
     * Test that a subuser with file permissions cannot access files on a suspended server.
     */
    public function test_subuser_cannot_access_files_on_suspended_server(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::FileRead]);
        $server->update(['status' => ServerState::Suspended]);

        $this->actingAs($user)
            ->getJson("/api/client/servers/$server->uuid/files/list")
            ->assertStatus(Response::HTTP_CONFLICT);
    }

    /**
     * Test that an admin without permission to update the server cannot access files
     * on a suspended server they have no direct access to.
     */
    public function test_admin_without_update_permission_cannot_access_files_on_suspended_server(): void
    {
        $server = $this->createServerModel(['status' => ServerState::Suspended]);

        /** @var User $user */
        $user = User::factory()->create();
        $role = Role::findOrCreate('view-only-test', 'web');
        $role->givePermissionTo(Permission::findOrCreate('view server', 'web'));
        $user->syncRoles($role);

        $this->actingAs($user)
            ->getJson("/api/client/servers/$server->uuid/files/list")
            ->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * Test that a root admin can still list files on a suspended server.
     */
    public function test_root_admin_can_access_files_on_suspended_server(): void
    {
        $server = $this->createServerModel(['status' => ServerState::Suspended]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->syncRoles(Role::getRootAdmin());

        $repository = \Mockery::mock(DaemonFileRepository::class);
        $this->app->instance(DaemonFileRepository::class, $repository);

        $repository->expects('setServer')
            ->with(\Mockery::on(fn ($value) => $server->uuid === $value->uuid))
            ->andReturnSelf()
            ->getMock()
            ->expects('getDirectory')
            ->with('/')
            ->andReturn([]);

        $this->actingAs($user)
            ->getJson("/api/client/servers/$server->uuid/files/list")
            ->assertOk();
    }

    /**
     * Test that the suspended bypass only covers the excepted routes; a root admin
     * still cannot use other endpoints on a suspended server.
     */
    public function test_root_admin_cannot_send_commands_to_suspended_server(): void
    {
        $server = $this->createServerModel(['status' => ServerState::Suspended]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->syncRoles(Role::getRootAdmin());

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/command", ['command' => 'say Test'])
            ->assertStatus(Response::HTTP_CONFLICT);
    }
}
