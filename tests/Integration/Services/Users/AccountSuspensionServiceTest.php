<?php

namespace App\Tests\Integration\Services\Users;

use App\Exceptions\DisplayException;
use App\Jobs\RevokeSftpAccessJob;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\AccountSuspensionService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class AccountSuspensionServiceTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([RevokeSftpAccessJob::class]);
    }

    public function test_account_can_be_suspended_without_affecting_owned_servers(): void
    {
        $actor = $this->rootAdmin();
        $user = User::factory()->create();
        $server = $this->createServerModel(['owner_id' => $user->id]);
        $rememberToken = $user->remember_token;

        $suspendedUser = $this->service()->suspend($actor, $user);

        $this->assertTrue($suspendedUser->isSuspended());
        $this->assertNotSame($rememberToken, $suspendedUser->remember_token);
        $this->assertFalse($server->refresh()->isSuspended());
        Bus::assertDispatched(RevokeSftpAccessJob::class);
    }

    public function test_account_can_be_unsuspended(): void
    {
        $actor = $this->rootAdmin();
        $user = User::factory()->create();
        $this->service()->suspend($actor, $user);

        $unsuspendedUser = $this->service()->unsuspend($actor, $user);

        $this->assertFalse($unsuspendedUser->isSuspended());
        $this->assertNull($unsuspendedUser->suspended_at);
    }

    public function test_database_sessions_are_deleted_when_an_account_is_suspended(): void
    {
        config()->set('session.driver', 'database');
        $actor = $this->rootAdmin();
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'suspended-user-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->service()->suspend($actor, $user);

        $this->assertDatabaseMissing('sessions', ['id' => 'suspended-user-session']);
    }

    public function test_users_cannot_suspend_themselves(): void
    {
        $actor = $this->rootAdmin();

        $this->expectException(DisplayException::class);
        $this->expectExceptionMessage(trans('admin/user.suspension.errors.cannot_suspend_self'));

        $this->service()->suspend($actor, $actor);
    }

    public function test_last_active_root_administrator_cannot_be_suspended(): void
    {
        $actor = $this->rootAdmin();
        $target = $this->rootAdmin();
        $actor->forceFill(['suspended_at' => now()])->save();

        $this->expectException(DisplayException::class);
        $this->expectExceptionMessage(trans('admin/user.suspension.errors.last_root_admin'));

        $this->service()->suspend($actor, $target);
    }

    public function test_update_permission_is_required_for_non_root_administrators(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->service()->suspend($actor, $target);
    }

    public function test_update_permission_allows_account_suspension(): void
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create(['guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('update user', 'web'));
        $actor->syncRoles($role);
        $target = User::factory()->create();

        $this->service()->suspend($actor, $target);

        $this->assertTrue($target->refresh()->isSuspended());
    }

    private function rootAdmin(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(Role::getRootAdmin());

        return $user;
    }

    private function service(): AccountSuspensionService
    {
        return $this->app->make(AccountSuspensionService::class);
    }
}
