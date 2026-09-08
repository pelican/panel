<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Data\Api\Client\ActivityLogData;
use App\Enums\SubuserPermission;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\ActivityLog;
use App\Models\Server;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Server - Activity log')]
class ActivityLogController extends ClientApiController
{
    /**
     * List activity logs
     *
     * Returns the activity logs for a server.
     *
     * @return array<array-key, mixed>
     */
    public function __invoke(ClientApiRequest $request, Server $server): array
    {
        Gate::authorize(SubuserPermission::ActivityRead, $server);

        $query = ActivityLog::whereHas('subjects', fn (Builder $query) => $query->where('subject_id', $server->id)->where('subject_type', $server->getMorphClass()))
            ->whereNotIn('activity_logs.event', ActivityLog::DISABLED_EVENTS)
            ->when(config('activity.hide_admin_activity'), fn (Builder $builder) => $builder->hideAdminActivity($server));

        $activity = QueryBuilder::for($query)
            ->allowedSorts(['timestamp'])
            ->allowedFilters([AllowedFilter::partial('event')])
            ->with('actor')
            ->paginate(min($request->query('per_page', '25'), 100))
            ->appends($request->query());

        return $this->response->collection($activity)
            ->transformWith(ActivityLogData::class)
            ->toArray();
    }
}
