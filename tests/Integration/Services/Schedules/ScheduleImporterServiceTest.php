<?php

namespace App\Tests\Integration\Services\Schedules;

use App\Enums\SubuserPermission;
use App\Exceptions\Http\HttpForbiddenException;
use App\Exceptions\Service\InvalidFileUploadException;
use App\Models\Schedule;
use App\Models\Task;
use App\Services\Schedules\Sharing\ScheduleImporterService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\UploadedFile;

class ScheduleImporterServiceTest extends IntegrationTestCase
{
    /**
     * Test that a schedule and its tasks are imported when the user has the required permissions.
     */
    public function test_schedule_is_imported(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::ScheduleCreate, SubuserPermission::ControlConsole]);

        $schedule = $this->getService()->fromFile($this->makeFile('command', 'say Test'), $server, $user);

        $this->assertSame($server->id, $schedule->server_id);
        $this->assertSame(1, $schedule->tasks()->count());
    }

    /**
     * Test that a schedule containing a task the user does not have permission for is not imported.
     */
    public function test_schedule_with_forbidden_task_is_not_imported(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::ScheduleCreate]);

        try {
            $this->getService()->fromFile($this->makeFile('command', 'op attacker'), $server, $user);
            $this->fail('Expected an HttpForbiddenException to be thrown.');
        } catch (HttpForbiddenException) {
        }

        $this->assertSame(0, Schedule::query()->where('server_id', $server->id)->count());
        $this->assertSame(0, Task::query()->count());
    }

    /**
     * Test that a schedule containing an unknown task action is not imported.
     */
    public function test_schedule_with_unknown_task_action_is_not_imported(): void
    {
        [$user, $server] = $this->generateTestAccount();

        $this->expectException(InvalidFileUploadException::class);

        $this->getService()->fromFile($this->makeFile('hodor', 'test'), $server, $user);
    }

    private function makeFile(string $action, string $payload): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('schedule.json', json_encode([
            'name' => 'Test',
            'is_active' => true,
            'only_when_online' => false,
            'tasks' => [
                ['sequence_id' => 1, 'action' => $action, 'payload' => $payload, 'time_offset' => 0, 'continue_on_failure' => false],
            ],
        ]));
    }

    private function getService(): ScheduleImporterService
    {
        return $this->app->make(ScheduleImporterService::class);
    }
}
