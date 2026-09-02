<?php

namespace App\Tests\Integration\Jobs;

use App\Enums\ServerState;
use App\Jobs\ProcessUserSuspensionServersJob;
use App\Models\User;
use App\Models\UserSuspension;
use App\Models\UserSuspensionServer;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Servers\SuspensionService;
use App\Tests\Integration\IntegrationTestCase;
use Mockery\MockInterface;

class ProcessUserSuspensionServersJobTest extends IntegrationTestCase
{
    public function test_it_suspends_and_then_unsuspends_only_the_recorded_server(): void
    {
        $user = User::factory()->create(['suspended_at' => now()]);
        $server = $this->createServerModel(['owner_id' => $user->id]);
        $suspension = UserSuspension::query()->create([
            'user_id' => $user->id,
            'reason' => 'Test',
            'suspend_servers' => true,
        ]);
        $record = $suspension->servers()->create([
            'server_id' => $server->id,
            'status' => UserSuspensionServer::STATUS_PENDING,
        ]);

        $repository = \Mockery::mock(DaemonServerRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer->sync')->twice()->andReturnSelf();
        });
        $service = new SuspensionService($repository);

        (new ProcessUserSuspensionServersJob($suspension->id))->handle($service);

        $this->assertSame(ServerState::Suspended, $server->refresh()->status);
        $this->assertSame(UserSuspensionServer::STATUS_SUSPENDED, $record->refresh()->status);

        $suspension->update(['lifted_at' => now()]);
        (new ProcessUserSuspensionServersJob($suspension->id, true))->handle($service);

        $this->assertNull($server->refresh()->status);
        $this->assertSame(UserSuspensionServer::STATUS_UNSUSPENDED, $record->refresh()->status);
    }

    public function test_it_does_not_suspend_a_server_after_the_account_suspension_is_lifted(): void
    {
        $user = User::factory()->create();
        $server = $this->createServerModel(['owner_id' => $user->id]);
        $suspension = UserSuspension::query()->create([
            'user_id' => $user->id,
            'reason' => 'Test',
            'suspend_servers' => true,
            'lifted_at' => now(),
        ]);
        $record = $suspension->servers()->create([
            'server_id' => $server->id,
            'status' => UserSuspensionServer::STATUS_PENDING,
        ]);

        $service = \Mockery::mock(SuspensionService::class);
        $service->shouldNotReceive('handle');

        (new ProcessUserSuspensionServersJob($suspension->id))->handle($service);

        $this->assertNull($server->refresh()->status);
        $this->assertSame(UserSuspensionServer::STATUS_PENDING, $record->refresh()->status);
    }
}
