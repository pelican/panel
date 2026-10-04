<?php

namespace App\Services\Allocations;

use App\Exceptions\DisplayException;
use App\Exceptions\Service\Allocation\AutoAllocationNotEnabledException;
use App\Exceptions\Service\Allocation\CidrOutOfRangeException;
use App\Exceptions\Service\Allocation\InvalidPortMappingException;
use App\Exceptions\Service\Allocation\NoAutoAllocationSpaceAvailableException;
use App\Exceptions\Service\Allocation\PortOutOfRangeException;
use App\Exceptions\Service\Allocation\TooManyPortsInRangeException;
use App\Models\Allocation;
use App\Models\Server;
use Webmozart\Assert\Assert;

class FindAssignableAllocationService
{
    /**
     * FindAssignableAllocationService constructor.
     */
    public function __construct(private AssignmentService $service) {}

    /**
     * Finds an existing unassigned allocation and attempts to assign it to the given server.
     *
     * Always attempts to find an existing unassigned allocation first. If create_new is enabled
     * and no unassigned allocation is available, creates a new one from the configured port range.
     * If create_new is disabled, throws an exception when no unassigned allocations are available.
     *
     * @throws DisplayException
     * @throws CidrOutOfRangeException
     * @throws InvalidPortMappingException
     * @throws PortOutOfRangeException
     * @throws TooManyPortsInRangeException
     */
    public function handle(Server $server): Allocation
    {
        throw_unless(config('panel.client_features.allocations.enabled'), new AutoAllocationNotEnabledException());
        throw_unless($server->allocation, new DisplayException(trans('server/network.no_primary')));

        $createNew = config('panel.client_features.allocations.create_new', true);

        // Attempt to find a given available allocation for a server. If one cannot be found
        // and create_new is enabled, we will fall back to attempting to create a new allocation
        // that can be used for the server.
        $start = config('panel.client_features.allocations.range_start', null);
        $end = config('panel.client_features.allocations.range_end', null);

        Assert::integerish($start);
        Assert::integerish($end);

        //
        // Note: We use withoutGlobalScopes() to bypass Filament's tenant scoping when called
        // from the Server panel context, which would otherwise filter allocations to only
        // those belonging to the current server (making it impossible to find unassigned ones)
        /** @var Allocation|null $allocation */
        $allocation = Allocation::withoutGlobalScopes()
            ->lockForUpdate()
            ->where('node_id', $server->node_id)
            ->when($server->allocation, function ($query) use ($server) {
                $query->where('ip', $server->allocation->ip);
            })
            ->whereBetween('port', [$start, $end])
            ->whereNull('server_id')
            ->inRandomOrder()
            ->first();

        // If create_new is disabled, only pick from existing allocations
        throw_if(!$createNew && !$allocation, new NoAutoAllocationSpaceAvailableException());

        // If create_new is enabled, create a new allocation if none available
        $allocation ??= $this->createNewAllocation($server, $start, $end);

        $allocation->update(['server_id' => $server->id]);

        return $allocation->refresh();
    }

    /**
     * Create a new allocation on the server's node with a random port from the defined range
     * in the settings. If there are no matches in that range, or something is wrong with the
     * range information provided an exception will be raised.
     *
     * @throws DisplayException
     * @throws CidrOutOfRangeException
     * @throws InvalidPortMappingException
     * @throws PortOutOfRangeException
     * @throws TooManyPortsInRangeException
     */
    protected function createNewAllocation(Server $server, ?int $start, ?int $end): Allocation
    {
        throw_if(!$start || !$end, new NoAutoAllocationSpaceAvailableException());

        // Get all the currently allocated ports for the node so that we can figure out
        // which port might be available.
        // Use withoutGlobalScopes() to bypass tenant filtering.
        $ports = Allocation::withoutGlobalScopes()
            ->where('node_id', $server->node_id)
            ->where('ip', $server->allocation->ip)
            ->whereBetween('port', [$start, $end])
            ->pluck('port');

        // Compute the difference of the range and the currently created ports, finding
        // any port that does not already exist in the database. We will then use this
        // array of ports to create a new allocation to assign to the server.
        $available = array_diff(range($start, $end), $ports->toArray());

        // If we've already allocated all the ports, just abort.
        throw_if(empty($available), new NoAutoAllocationSpaceAvailableException());

        // Pick a random port out of the remaining available ports.
        /** @var int $port */
        $port = $available[array_rand($available)];

        $this->service->handle($server->node, [
            'allocation_ip' => $server->allocation->ip,
            'allocation_ports' => [$port],
        ]);

        /** @var Allocation $allocation */
        $allocation = Allocation::withoutGlobalScopes()
            ->lockForUpdate()
            ->where('node_id', $server->node_id)
            ->where('ip', $server->allocation->ip)
            ->where('port', $port)
            ->firstOrFail();

        return $allocation;
    }
}
