<?php

namespace App\Tests\Integration\Services\Users;

use App\Enums\ServerState;
use App\Exceptions\DisplayException;
use App\Jobs\ProcessUserSuspensionServersJob;
use App\Jobs\RevokeSftpAccessJob;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSuspensionServer;
use App\Services\Users\AccountSuspensionService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;

class AccountSuspensionServiceTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([ProcessUserSuspensionServersJob::class, RevokeSftpAccessJob::class]);
    }

    public function test_account_can_be_suspended_without_affecting_owned_servers(): void
    {
        $actor = $this->rootAdmin();
        $user = User::factory()->create();
        $server = $this->createServerModel(['owner_id' => $user->id]);
        $version = $user->auth_session_version;
        $rememberToken = $user->remember_token;

        $suspension = $this->service()->suspend($actor, $user, 'Chargeback investigation', false);

        $user->refresh();
        $this->assertTrue($user->isSuspended());
        $this->assertSame($version + 1, $user->auth_session_version);
        $this->assertNotSame($rememberToken, $user->remember_token);
        $this->assertFalse($server->refresh()->isSuspended());
        $this->assertFalse($suspension->suspend_servers);
        $this->assertSame('Chargeback investigation', $suspension->reason);
        $this->assertDatabaseCount('user_suspension_servers', 0);
        Bus::assertNotDispatched(ProcessUserSuspensionServersJob::class);
    }

    public function test_owned_servers_are_tracked_without_claiming_existing_suspensions(): void
    {
        $actor = $this->rootAdmin();
        $user = User::factory()->create();
        $eligible = $this->createServerModel(['owner_id' => $user->id]);
        $alreadySuspended = $this->createServerModel([
            'owner_id' => $user->id,
            'status' => ServerState::Suspended,
        ]);

        $suspension = $this->service()->suspend($actor, $user, 'Policy violation', true);

        $this->assertDatabaseHas('user_suspension_servers', [
            'user_suspension_id' => $suspension->id,
            'server_id' => $eligible->id,
            'status' => UserSuspensionServer::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('user_suspension_servers', [
            'user_suspension_id' => $suspension->id,
            'server_id' => $alreadySuspended->id,
            'status' => UserSuspensionServer::STATUS_SKIPPED,
        ]);
        Bus::assertDispatched(ProcessUserSuspensionServersJob::class, fn ($job) => $job->suspensionId === $suspension->id && !$job->unsuspend);
    }

    public function test_lifting_suspension_can_queue_only_servers_changed_by_that_suspension(): void
    {
        $actor = $this->rootAdmin();
        $user = User::factory()->create();
        $server = $this->createServerModel(['owner_id' => $user->id]);
        $suspension = $this->service()->suspend($actor, $user, 'Temporary review', true);
        $suspension->servers()->where('server_id', $server->id)->update([
            'status' => UserSuspensionServer::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ]);

        $this->service()->unsuspend($actor, $user, true);

        $this->assertFalse($user->refresh()->isSuspended());
        $this->assertNotNull($suspension->refresh()->lifted_at);
        Bus::assertDispatched(ProcessUserSuspensionServersJob::class, fn ($job) => $job->suspensionId === $suspension->id && $job->unsuspend);
    }

    public function test_users_cannot_suspend_themselves(): void
    {
        $actor = $this->rootAdmin();

        $this->expectException(DisplayException::class);
        $this->expectExceptionMessage('You cannot suspend your own account.');

        $this->service()->suspend($actor, $actor, 'Invalid action', false);
    }

    public function test_suspension_reason_cannot_be_blank(): void
    {
        $actor = $this->rootAdmin();
        $target = User::factory()->create();

        $this->expectException(DisplayException::class);
        $this->expectExceptionMessage('A suspension reason is required.');

        $this->service()->suspend($actor, $target, '   ', false);
    }

    public function test_last_active_root_administrator_cannot_be_suspended(): void
    {
        $actor = $this->rootAdmin();
        $target = $this->rootAdmin();
        $actor->forceFill(['suspended_at' => now()])->save();

        $this->expectException(DisplayException::class);
        $this->expectExceptionMessage('The last active root administrator cannot be suspended.');

        $this->service()->suspend($actor, $target, 'Invalid action', false);
    }

    public function test_specific_permission_is_required_for_non_root_administrators(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->service()->suspend($actor, $target, 'Invalid action', false);
    }

    public function test_suspend_permission_allows_account_only_mode(): void
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create(['guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('suspend user', 'web'));
        $actor->syncRoles($role);
        $target = User::factory()->create();

        $this->service()->suspend($actor, $target, 'Manual review', false);

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
