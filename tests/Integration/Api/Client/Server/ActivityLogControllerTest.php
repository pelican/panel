<?php

namespace App\Tests\Integration\Api\Client\Server;

use App\Enums\SubuserPermission;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;
use Spatie\Permission\Models\Permission;

class ActivityLogControllerTest extends ClientApiIntegrationTestCase
{
    /**
     * Test that activity from an admin with a custom role is hidden from the server's
     * activity log when the hide admin activity option is enabled.
     */
    public function test_custom_role_admin_activity_is_hidden_when_enabled(): void
    {
        config()->set('activity.hide_admin_activity', true);

        [$user, $server] = $this->generateTestAccount();

        /** @var User $admin */
        $admin = User::factory()->create();
        /** @var Role $role */
        $role = Role::factory()->create(['name' => 'Support', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('view server', 'web'));
        $admin->syncRoles($role);

        $log = $this->createActivity($server, $admin);

        $response = $this->actingAs($user)->getJson($this->link($server, '/activity'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonMissing(['id' => sha1((string) $log->id)]);
    }

    /**
     * Test that activity from a root admin is hidden from the server's activity
     * log when the hide admin activity option is enabled.
     */
    public function test_root_admin_activity_is_hidden_when_enabled(): void
    {
        config()->set('activity.hide_admin_activity', true);

        [$user, $server] = $this->generateTestAccount();

        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->syncRoles(Role::getRootAdmin());

        $this->createActivity($server, $admin);

        $this->actingAs($user)
            ->getJson($this->link($server, '/activity'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Test that activity from the server owner, a subuser (even one holding a role),
     * and the system is still shown when the hide admin activity option is enabled.
     */
    public function test_member_and_system_activity_is_shown_when_enabled(): void
    {
        config()->set('activity.hide_admin_activity', true);

        [$subuser, $server] = $this->generateTestAccount([SubuserPermission::ActivityRead]);
        /** @var Role $role */
        $role = Role::factory()->create(['name' => 'Support', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('view server', 'web'));
        $subuser->syncRoles($role);

        $this->createActivity($server, $server->user);
        $this->createActivity($server, $subuser);
        $this->createActivity($server, null);

        $this->actingAs($server->user)
            ->getJson($this->link($server, '/activity'))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test that admin activity is shown when the hide admin activity option is disabled.
     */
    public function test_admin_activity_is_shown_when_disabled(): void
    {
        config()->set('activity.hide_admin_activity', false);

        [$user, $server] = $this->generateTestAccount();

        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->syncRoles(Role::getRootAdmin());

        $this->createActivity($server, $admin);

        $this->actingAs($user)
            ->getJson($this->link($server, '/activity'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    private function createActivity(Server $server, ?User $actor): ActivityLog
    {
        $log = new ActivityLog([
            'event' => 'server:file.read',
            'ip' => '127.0.0.1',
            'properties' => [],
        ]);

        if ($actor) {
            $log->actor()->associate($actor);
        }

        $log->save();

        $log->subjects()->create([
            'subject_id' => $server->id,
            'subject_type' => $server->getMorphClass(),
        ]);

        return $log;
    }
}
