<?php

namespace App\Http\Requests\Api\Client\Servers\Schedules;

use App\Enums\SubuserPermission;
use App\Extensions\Tasks\TaskService;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends ViewScheduleRequest
{
    /**
     * Determine if the user is allowed to create a new task for this schedule. We check if they
     * can modify a schedule here, the permissions required by the specific task action are
     * checked by the controller.
     */
    public function permission(): SubuserPermission
    {
        return SubuserPermission::ScheduleUpdate;
    }

    public function rules(): array
    {
        return [
            /** What the task does when it runs. */
            'action' => ['required', Rule::in(array_keys(app(TaskService::class)->getAll()))], // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
            /** Argument for the action: the console command, the power signal, or the paths to skip or delete. */
            'payload' => 'required_unless:action,backup|string|nullable',
            /** Seconds to wait after the previous task finishes before this one runs. */
            'time_offset' => 'required|numeric|min:0|max:900',
            /** Position of the task within the schedule. Tasks run in ascending order. */
            'sequence_id' => 'sometimes|required|numeric|min:1',
            /** Keep running the remaining tasks even if this one fails. */
            'continue_on_failure' => 'sometimes|required|boolean',
        ];
    }
}
