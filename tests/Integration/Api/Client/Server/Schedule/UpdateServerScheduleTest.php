<?php

namespace App\Tests\Integration\Api\Client\Server\Schedule;

use App\Enums\SubuserPermission;
use App\Helpers\Utilities;
use App\Models\Schedule;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class UpdateServerScheduleTest extends ClientApiIntegrationTestCase
{
    /**
     * The data to use when updating a schedule.
     */
    private array $updateData = [
        'name' => 'Updated Schedule Name',
        'minute' => '5',
        'hour' => '*',
        'day_of_week' => '*',
        'month' => '*',
        'day_of_month' => '*',
        'is_active' => false,
    ];

    /**
     * Test that a schedule can be updated.
     */
    #[DataProvider('permissionsDataProvider')]
    public function test_schedule_can_be_updated(array $permissions): void
    {
        [$user, $server] = $this->generateTestAccount($permissions);

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id]);
        $expected = Utilities::getScheduleNextRunDate('5', '*', '*', '*', '*');

        $response = $this->actingAs($user)
            ->postJson("/api/client/servers/{$server->uuid}/schedules/{$schedule->id}", $this->updateData);

        $schedule = $schedule->refresh();

        $response->assertOk();
        $this->assertSame('Updated Schedule Name', $schedule->name);
        $this->assertFalse($schedule->is_active);
        $this->assertJsonTransformedWith($response->json('attributes'), $schedule);

        $this->assertSame($expected->toAtomString(), $schedule->next_run_at->toAtomString());
    }

    /**
     * Test that updating a schedule evaluates the cron in the schedule's timezone.
     */
    public function test_next_run_uses_schedule_timezone(): void
    {
        [$user, $server] = $this->generateTestAccount();

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create(['server_id' => $server->id, 'timezone' => 'America/New_York']);
        $data = array_merge($this->updateData, ['minute' => '0', 'hour' => '3']);

        $this->actingAs($user)
            ->postJson("/api/client/servers/{$server->uuid}/schedules/{$schedule->id}", $data)
            ->assertOk();

        $nextRun = $schedule->refresh()->next_run_at->timezone('America/New_York');

        $this->assertSame('03:00', $nextRun->format('H:i'));
    }

    /**
     * Test that an error is returned if the schedule exists but does not belong to this
     * specific server instance.
     */
    public function test_error_is_returned_if_schedule_does_not_belong_to_server(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $server2 = $this->createServerModel(['owner_id' => $user->id]);

        $schedule = Schedule::factory()->create(['server_id' => $server2->id]);

        $this->actingAs($user)
            ->postJson("/api/client/servers/{$server->uuid}/schedules/{$schedule->id}")
            ->assertNotFound();
    }

    /**
     * Test that an error is returned if the subuser does not have permission to modify a
     * server schedule.
     */
    public function test_error_is_returned_if_subuser_does_not_have_permission_to_modify_schedule(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::ScheduleCreate]);

        $schedule = Schedule::factory()->create(['server_id' => $server->id]);

        $this->actingAs($user)
            ->postJson("/api/client/servers/{$server->uuid}/schedules/{$schedule->id}")
            ->assertForbidden();
    }

    /**
     * Test that the "is_processing" field gets reset to false when the schedule is enabled
     * or disabled so that an invalid state can be more easily fixed.
     */
    public function test_schedule_is_processing_is_set_to_false_when_active_state_changes(): void
    {
        [$user, $server] = $this->generateTestAccount();

        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create([
            'server_id' => $server->id,
            'is_active' => true,
            'is_processing' => true,
        ]);

        $this->assertTrue($schedule->is_active);
        $this->assertTrue($schedule->is_processing);

        $response = $this->actingAs($user)
            ->postJson("/api/client/servers/{$server->uuid}/schedules/{$schedule->id}", $this->updateData);

        $schedule = $schedule->refresh();

        $response->assertOk();
        $this->assertFalse($schedule->is_active);
        $this->assertFalse($schedule->is_processing);
    }

    public static function permissionsDataProvider(): array
    {
        return [[[]], [[SubuserPermission::ScheduleUpdate]]];
    }
}
