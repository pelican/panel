<?php

namespace App\Tests\Integration\Api\Client\Server\ScheduleTask;

use App\Enums\SubuserPermission;
use App\Models\Schedule;
use App\Models\Task;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateServerScheduleTaskTest extends ClientApiIntegrationTestCase
{
    /**
     * Test that a task can be created.
     */
    #[DataProvider('permissionsDataProvider')]
    public function test_task_can_be_created(array $permissions): void
    {
        [$user, $server] = $this->generateTestAccount($permissions);

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);
        $this->assertEmpty($schedule->tasks);

        $response = $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), [
            'action' => 'command',
            'payload' => 'say Test',
            'time_offset' => 10,
            'sequence_id' => 1,
        ]);

        $response->assertOk();
        /** @var Task $task */
        $task = Task::query()->findOrFail($response->json('attributes.id'));

        $this->assertSame($schedule->id, $task->schedule_id);
        $this->assertSame(1, $task->sequence_id);
        $this->assertSame('command', $task->action);
        $this->assertSame('say Test', $task->payload);
        $this->assertSame(10, $task->time_offset);
        $this->assertJsonTransformedWith($response->json('attributes'), $task);
    }

    /**
     * Test that validation errors are returned correctly if bad data is passed into the API.
     */
    public function test_validation_errors_are_returned(): void
    {
        [$user, $server] = $this->generateTestAccount();

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);

        $response = $this->actingAs($user)->postJson($this->link($schedule, '/tasks'))->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        foreach (['action', 'payload', 'time_offset'] as $i => $field) {
            $response->assertJsonPath("errors.$i.meta.rule", $field === 'payload' ? 'required_unless' : 'required');
            $response->assertJsonPath("errors.$i.meta.source_field", $field);
        }

        $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), [
            'action' => 'hodor',
            'payload' => 'say Test',
            'time_offset' => 0,
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.0.meta.rule', 'in')
            ->assertJsonPath('errors.0.meta.source_field', 'action');

        $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), [
            'action' => 'command',
            'time_offset' => 0,
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.0.meta.rule', 'required_unless')
            ->assertJsonPath('errors.0.meta.source_field', 'payload');

        $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), [
            'action' => 'command',
            'payload' => 'say Test',
            'time_offset' => 0,
            'sequence_id' => 'hodor',
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('errors.0.meta.rule', 'numeric')
            ->assertJsonPath('errors.0.meta.source_field', 'sequence_id');
    }

    /**
     * Test that backups can not be tasked when the backup limit is 0.
     */
    public function test_backups_can_not_be_tasked_if_limit0(): void
    {
        [$user, $server] = $this->generateTestAccount();

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);

        $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), [
            'action' => 'backup',
            'time_offset' => 0,
        ])
            ->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('errors.0.detail', 'A backup task cannot be created when the server\'s backup limit is set to 0.');

        $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), [
            'action' => 'backup',
            'payload' => "file.txt\nfile2.log",
            'time_offset' => 0,
        ])
            ->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('errors.0.detail', 'A backup task cannot be created when the server\'s backup limit is set to 0.');
    }

    /**
     * Test that an error is returned if the user attempts to create an additional task that
     * would put the schedule over the task limit.
     */
    public function test_error_is_returned_if_too_many_tasks_exist_for_schedule(): void
    {
        config()->set('panel.client_features.schedules.per_schedule_task_limit', 2);

        [$user, $server] = $this->generateTestAccount();

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);
        Task::factory()->times(2)->create(['schedule_id' => $schedule->id]);

        $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), [
            'action' => 'command',
            'payload' => 'say test',
            'time_offset' => 0,
        ])
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonPath('errors.0.code', 'ServiceLimitExceededException')
            ->assertJsonPath('errors.0.detail', 'Schedules may not have more than 2 tasks associated with them. Creating this task would put this schedule over the limit.');
    }

    /**
     * Test that an error is returned if the targeted schedule does not belong to the server
     * in the request.
     */
    public function test_error_is_returned_if_schedule_does_not_belong_to_server(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $server2 = $this->createServerModel(['owner_id' => $user->id]);

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server2->id]);

        $this->actingAs($user)
            ->postJson("/api/client/servers/$server->uuid/schedules/$schedule->id/tasks")
            ->assertNotFound();
    }

    /**
     * Test that an error is returned if the subuser making the request does not have permission
     * to update a schedule.
     */
    public function test_error_is_returned_if_subuser_does_not_have_schedule_update_permissions(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::ScheduleCreate]);

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);

        $this->actingAs($user)
            ->postJson($this->link($schedule, '/tasks'))
            ->assertForbidden();
    }

    /**
     * Test that a subuser can only create tasks for actions they have the permissions for.
     */
    #[DataProvider('actionPermissionsDataProvider')]
    public function test_subuser_requires_action_permission_to_create_task(string $action, string $payload, SubuserPermission $permission): void
    {
        $data = ['action' => $action, 'payload' => $payload, 'time_offset' => 0];

        [$user, $server] = $this->generateTestAccount([SubuserPermission::ScheduleUpdate]);
        $server->update(['backup_limit' => 1]);

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);

        $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), $data)
            ->assertForbidden()
            ->assertJsonPath('errors.0.detail', trans('server/schedule.tasks.no_permission'));

        $this->assertEmpty($schedule->tasks()->get());

        [$user, $server] = $this->generateTestAccount([SubuserPermission::ScheduleUpdate, $permission]);
        $server->update(['backup_limit' => 1]);

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);

        $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), $data)->assertOk();
    }

    /**
     * Test that a subuser can only create power tasks for the signals they have the permissions for.
     */
    public function test_subuser_requires_matching_control_permission_for_power_signal(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::ScheduleUpdate, SubuserPermission::ControlStart]);

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);

        $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), [
            'action' => 'power',
            'payload' => 'start',
            'time_offset' => 0,
        ])->assertOk();

        foreach (['stop', 'restart', 'kill', 'hodor'] as $signal) {
            $this->actingAs($user)->postJson($this->link($schedule, '/tasks'), [
                'action' => 'power',
                'payload' => $signal,
                'time_offset' => 0,
            ])->assertForbidden();
        }

        $this->assertSame(1, $schedule->tasks()->count());
    }

    /**
     * Test that a subuser can not update an existing task to an action they do not have the permissions for.
     */
    public function test_subuser_requires_action_permission_to_update_task(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::ScheduleUpdate, SubuserPermission::ControlStart]);

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);
        /** @var Task $task */
        $task = Task::factory()->create(['schedule_id' => $schedule->id, 'action' => 'power', 'payload' => 'start']);

        $this->actingAs($user)->postJson($this->link($schedule, "/tasks/$task->id"), [
            'action' => 'command',
            'payload' => 'op attacker',
            'time_offset' => 0,
        ])->assertForbidden();

        $task->refresh();
        $this->assertSame('power', $task->action);
        $this->assertSame('start', $task->payload);
    }

    public static function permissionsDataProvider(): array
    {
        return [[[]], [[SubuserPermission::ScheduleUpdate, SubuserPermission::ControlConsole]]];
    }

    public static function actionPermissionsDataProvider(): array
    {
        return [
            'command' => ['command', 'say Test', SubuserPermission::ControlConsole],
            'power start' => ['power', 'start', SubuserPermission::ControlStart],
            'power stop' => ['power', 'stop', SubuserPermission::ControlStop],
            'power kill' => ['power', 'kill', SubuserPermission::ControlStop],
            'power restart' => ['power', 'restart', SubuserPermission::ControlRestart],
            'delete files' => ['delete_files', 'file.txt', SubuserPermission::FileDelete],
            'backup' => ['backup', '', SubuserPermission::BackupCreate],
        ];
    }
}
