<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Data\Api\Client\ActivityLogData;
use App\Enums\SubuserPermission;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\ActivityLog;
use App\Models\Server;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
        throw_unless($request->user()->can(SubuserPermission::ActivityRead, $server), new AuthorizationException());

        $query = ActivityLog::whereHas('subjects', fn (Builder $query) => $query->where('subject_id', $server->id)->where('subject_type', $server->getMorphClass()))
            ->visibleEvents()
            ->when(config('activity.hide_admin_activity'), fn (Builder $builder) => $builder->hideAdminActivity($server));

        $activity = QueryBuilder::for($query)
            ->allowedSorts('timestamp')
            ->allowedFilters(AllowedFilter::partial('event'))
            // UserData reads the actor's roles to report root_admin.
            ->with(['actor' => fn (MorphTo $actor) => $actor->morphWith([User::class => ['roles']])])
            ->paginate(min($request->query('per_page', '25'), 100))
            ->appends($request->query());

        return $this->response->collection($activity)
            ->transformWith(ActivityLogData::class)
            ->toArray();
    }
}
