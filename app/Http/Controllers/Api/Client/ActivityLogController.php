<?php

namespace App\Http\Controllers\Api\Client;

use App\Data\Api\Client\ActivityLogData;
use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityLogController extends ClientApiController
{
    /**
     * List activity logs
     *
     * Returns a paginated set of the user's activity logs.
     *
     * @return array<array-key, mixed>
     */
    public function __invoke(ClientApiRequest $request): array
    {
        $activity = QueryBuilder::for($request->user()->activity()->visibleEvents())
            ->allowedFilters(AllowedFilter::partial('event'))
            ->allowedSorts('timestamp')
            // UserData reads the actor's roles to report root_admin.
            ->with(['actor' => fn (MorphTo $actor) => $actor->morphWith([User::class => ['roles']])])
            ->paginate(min($request->query('per_page', '25'), 100))
            ->appends($request->query());

        return $this->response->collection($activity)
            ->transformWith(ActivityLogData::class)
            ->toArray();
    }
}
