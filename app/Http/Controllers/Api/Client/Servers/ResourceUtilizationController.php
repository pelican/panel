<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Data\Api\Client\StatsData;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\GetServerRequest;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;

#[Group('Server', weight: 3)]
class ResourceUtilizationController extends ClientApiController
{
    /**
     * ResourceUtilizationController constructor.
     */
    public function __construct(private Repository $cache, private DaemonServerRepository $repository)
    {
        parent::__construct();
    }

    /**
     * View resources
     *
     * Return the current resource utilization for a server. This value is cached for up to
     * 20 seconds at a time to ensure that repeated requests to this endpoint do not cause
     * a flood of unnecessary API calls.
     *
     * @return array<array-key, mixed>
     *
     * @throws ConnectionException
     */
    public function __invoke(GetServerRequest $request, Server $server): array
    {
        $key = "resources:$server->uuid";
        $stats = $this->cache->remember($key, Carbon::now()->addSeconds(20), function () use ($server) {
            return $this->repository->setServer($server)->getDetails();
        });

        return $this->response->item($stats)
            ->transformWith(StatsData::class)
            ->toArray();
    }
}
