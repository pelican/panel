<?php

namespace App\Http\Requests\Api\Client\Servers\Schedules;

use App\Enums\SubuserPermission;
use App\Models\Schedule;

class StoreScheduleRequest extends ViewScheduleRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::ScheduleCreate;
    }

    public function rules(): array
    {
        $rules = Schedule::getRules();

        return [
            /** Name the schedule is shown under in the Panel. */
            'name' => $rules['name'],
            /** Whether the schedule runs at all. Inactive schedules keep their settings but never fire. */
            'is_active' => array_merge(['filled'], $rules['is_active']),
            /** Skip the run when the server is not online at the scheduled time. */
            'only_when_online' => $rules['only_when_online'],
            /** Minute field of the cron expression, such as `0` or `*`. */
            'minute' => $rules['cron_minute'],
            /** Hour field of the cron expression. */
            'hour' => $rules['cron_hour'],
            /** Day of month field of the cron expression. */
            'day_of_month' => $rules['cron_day_of_month'],
            /** Month field of the cron expression. */
            'month' => $rules['cron_month'],
            /** Day of week field of the cron expression, where `0` is Sunday. */
            'day_of_week' => $rules['cron_day_of_week'],
        ];
    }
}
