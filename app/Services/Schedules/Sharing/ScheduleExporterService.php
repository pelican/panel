<?php

namespace App\Services\Schedules\Sharing;

use App\Models\Schedule;
use App\Models\Task;

class ScheduleExporterService
{
    public function handle(Schedule|int $schedule): string
    {
        if (!$schedule instanceof Schedule) {
            $schedule = Schedule::findOrFail($schedule);
        }

        $data = [
            'name' => $schedule->name,
            'is_active' => $schedule->is_active,
            'only_when_online' => $schedule->only_when_online,
            'cron_minute' => $schedule->cron_minute,
            'cron_hour' => $schedule->cron_hour,
            'cron_day_of_month' => $schedule->cron_day_of_month,
            'cron_month' => $schedule->cron_month,
            'cron_day_of_week' => $schedule->cron_day_of_week,

            'tasks' => $schedule->tasks->map(function (Task $task) {
                return [
                    'sequence_id' => $task->sequence_id,
                    'action' => $task->action,
                    'payload' => $task->payload,
                    'time_offset' => $task->time_offset,
                    'continue_on_failure' => $task->continue_on_failure,
                ];
            }),
        ];

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }
}
