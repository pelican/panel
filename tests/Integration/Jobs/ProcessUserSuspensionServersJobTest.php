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
use RuntimeException;

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
        $user->forceFill(['suspended_at' => null])->save();
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

    public function test_it_rechecks_the_lift_state_before_each_server(): void
    {
        $user = User::factory()->create(['suspended_at' => now()]);
        $firstServer = $this->createServerModel(['owner_id' => $user->id]);
        $secondServer = $this->createServerModel(['owner_id' => $user->id]);
        $suspension = UserSuspension::query()->create([
            'user_id' => $user->id,
            'reason' => 'Test',
            'suspend_servers' => true,
        ]);
        $firstRecord = $suspension->servers()->create([
            'server_id' => $firstServer->id,
            'status' => UserSuspensionServer::STATUS_PENDING,
        ]);
        $secondRecord = $suspension->servers()->create([
            'server_id' => $secondServer->id,
            'status' => UserSuspensionServer::STATUS_PENDING,
        ]);

        $repository = \Mockery::mock(DaemonServerRepository::class, function (MockInterface $mock) use ($suspension) {
            $mock->expects('setServer->sync')->once()->andReturnUsing(function () use ($suspension) {
                $suspension->update(['lifted_at' => now()]);
            });
        });

        (new ProcessUserSuspensionServersJob($suspension->id))->handle(new SuspensionService($repository));

        $this->assertTrue($firstServer->refresh()->isSuspended());
        $this->assertSame(UserSuspensionServer::STATUS_SUSPENDED, $firstRecord->refresh()->status);
        $this->assertFalse($secondServer->refresh()->isSuspended());
        $this->assertSame(UserSuspensionServer::STATUS_PENDING, $secondRecord->refresh()->status);
    }

    public function test_failed_daemon_sync_rolls_back_and_keeps_the_record_retryable(): void
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
            $mock->expects('setServer->sync')->once()->andThrow(new RuntimeException('Temporary daemon failure'));
        });

        try {
            (new ProcessUserSuspensionServersJob($suspension->id))->handle(new SuspensionService($repository));
            $this->fail('The daemon exception should be rethrown for the queue to retry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Temporary daemon failure', $exception->getMessage());
        }

        $this->assertFalse($server->refresh()->isSuspended());
        $this->assertSame(UserSuspensionServer::STATUS_PENDING, $record->refresh()->status);
        $this->assertSame('Temporary daemon failure', $record->error);
    }

    public function test_a_failed_server_does_not_prevent_later_servers_from_being_processed(): void
    {
        $user = User::factory()->create(['suspended_at' => now()]);
        $failedServer = $this->createServerModel(['owner_id' => $user->id]);
        $healthyServer = $this->createServerModel(['owner_id' => $user->id]);
        $suspension = UserSuspension::query()->create([
            'user_id' => $user->id,
            'reason' => 'Test',
            'suspend_servers' => true,
        ]);
        $failedRecord = $suspension->servers()->create([
            'server_id' => $failedServer->id,
            'status' => UserSuspensionServer::STATUS_PENDING,
        ]);
        $healthyRecord = $suspension->servers()->create([
            'server_id' => $healthyServer->id,
            'status' => UserSuspensionServer::STATUS_PENDING,
        ]);

        $repository = \Mockery::mock(DaemonServerRepository::class, function (MockInterface $mock) {
            $mock->expects('setServer')->twice()->andReturnSelf();
            $mock->expects('sync')->twice()->andReturnUsing(function () {
                static $attempt = 0;

                if (++$attempt === 1) {
                    throw new RuntimeException('Temporary daemon failure');
                }
            });
        });

        $job = new ProcessUserSuspensionServersJob($suspension->id);
        $failure = null;

        try {
            $job->handle(new SuspensionService($repository));
            $this->fail('The daemon exception should be rethrown after processing the batch.');
        } catch (RuntimeException $exception) {
            $failure = $exception;
            $this->assertSame('Temporary daemon failure', $exception->getMessage());
        }

        $this->assertFalse($failedServer->refresh()->isSuspended());
        $this->assertSame(UserSuspensionServer::STATUS_PENDING, $failedRecord->refresh()->status);
        $this->assertSame('Temporary daemon failure', $failedRecord->error);
        $this->assertTrue($healthyServer->refresh()->isSuspended());
        $this->assertSame(UserSuspensionServer::STATUS_SUSPENDED, $healthyRecord->refresh()->status);

        $this->assertInstanceOf(RuntimeException::class, $failure);
        $job->failed($failure);

        $this->assertSame(UserSuspensionServer::STATUS_FAILED, $failedRecord->refresh()->status);
        $this->assertSame(UserSuspensionServer::STATUS_SUSPENDED, $healthyRecord->refresh()->status);
    }
}
