<?php

namespace App\Http\Controllers\Api\Client;

use App\Data\Api\Client\ServerData;
use App\Http\Requests\Api\Client\GetServersRequest;
use App\Models\Filters\MultiFieldServerFilter;
use App\Models\Server;
use App\Models\Subuser;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Base')]
class ClientController extends ClientApiController
{
    /**
     * ClientController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * List servers
     *
     * Return all the servers available to the client making the API
     * request, including servers the user has access to as a subuser.
     *
     * @return array<array-key, mixed>
     */
    public function index(GetServersRequest $request): array
    {
        $user = $request->user();

        // Only eager load the relationships that map to includes the resource honors.
        $includes = array_values(array_intersect($this->parseIncludes(), ServerData::$availableIncludes));

        /** @var Builder<Model> $query */
        $query = Server::query()->with(array_merge($includes, ['node']));

        // Start the query builder and ensure we eager load any requested relationships from the request.
        $builder = QueryBuilder::for($query)->allowedFilters([
            'uuid',
            'name',
            'description',
            'external_id',
            AllowedFilter::custom('*', new MultiFieldServerFilter()),
        ]);

        $type = $request->input('type');
        // Either return all the servers the user has access to because they are an admin `?type=admin` or
        // just return all the servers the user has access to because they are the owner or a subuser of the
        // server. If ?type=admin-all is passed all servers on the system will be returned to the user, rather
        // than only servers they can see because they are an admin.
        if (in_array($type, ['admin', 'admin-all'])) {
            // If they aren't an admin but want all the admin servers don't fail the request, just
            // make it a query that will never return any results back.
            if (!$user->isRootAdmin()) {
                $builder->whereRaw('1 = 2');
            } else {
                $builder = $type === 'admin-all'
                    ? $builder
                    : $builder->whereNotIn('servers.id', $user->directAccessibleServers()->pluck('id')->all());
            }
        } elseif ($type === 'owner') {
            $builder = $builder->where('servers.owner_id', $user->id);
        } else {
            $builder = $builder->whereIn('servers.id', $user->directAccessibleServers()->pluck('id')->all());
        }

        $servers = $builder->paginate(min($request->query('per_page', '50'), 100))->appends($request->query());

        return $this->response->transformWith(ServerData::class)->collection($servers)->toArray();
    }

    /**
     * List subuser permissions
     *
     * Returns all the subuser permissions available on the system.
     *
     * @return array{object: string, attributes: array{permissions: string[]}}
     */
    public function permissions(): array
    {
        return [
            'object' => 'system_permissions',
            'attributes' => [
                'permissions' => Subuser::allPermissionKeys(),
            ],
        ];
    }
}
