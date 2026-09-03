<?php

namespace App\Services\Servers;

use App\Enums\ServerState;
use App\Exceptions\DisplayException;
use App\Exceptions\Model\DataValidationException;
use App\Exceptions\Service\Deployment\NoViableAllocationException;
use App\Exceptions\Service\Deployment\NoViableNodeException;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Objects\DeploymentObject;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Deployment\AllocationSelectionService;
use App\Services\Deployment\FindViableNodesService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use Throwable;
use Webmozart\Assert\Assert;

class ServerCreationService
{
    /**
     * ServerCreationService constructor.
     */
    public function __construct(
        private AllocationSelectionService $allocationSelectionService,
        private ConnectionInterface $connection,
        private DaemonServerRepository $daemonServerRepository,
        private FindViableNodesService $findViableNodesService,
        private ServerDeletionService $serverDeletionService,
        private VariableValidatorService $validatorService
    ) {}

    /**
     * Create a server on the Panel and trigger a request to the Daemon to begin the server
     * creation process. This function will attempt to set as many additional values
     * as possible given the input data. For example, if an allocation_id is passed with
     * no node_id the node_is will be picked from the allocation.
     *
     * @param  array<mixed, mixed>  $data
     *
     * @throws Throwable
     * @throws DisplayException
     * @throws ValidationException
     * @throws NoViableAllocationException
     */
    public function handle(array $data, ?DeploymentObject $deployment = null): Server
    {
        if (!isset($data['oom_killer']) && isset($data['oom_disabled'])) {
            $data['oom_killer'] = !$data['oom_disabled'];
        }

        /** @var Egg $egg */
        $egg = Egg::query()->findOrFail($data['egg_id']);

        // Fill missing fields from egg
        $data['image'] ??= Arr::first($egg->docker_images);
        $data['startup'] ??= Arr::first($egg->startup_commands);

        // If a deployment object has been passed we need to get the allocation and node that the server should use.
        if ($deployment) {
            $nodes = $this->findViableNodesService->handle(
                Arr::get($data, 'memory', 0),
                Arr::get($data, 'disk', 0),
                Arr::get($data, 'cpu', 0),
                $deployment->getTags(),
            )->pluck('id');

            throw_if($nodes->isEmpty(), new NoViableNodeException(trans('exceptions.deployment.no_viable_nodes')));

            $ports = $deployment->getPorts();
            if (!empty($ports)) {
                $allocation = $this->allocationSelectionService->setDedicated($deployment->isDedicated())
                    ->setNodes($nodes->toArray())
                    ->setPorts($ports)
                    ->handle();

                $data['allocation_id'] = $allocation->id;
                $data['node_id'] = $allocation->node_id;
            }

            if (empty($data['node_id'])) {
                $data['node_id'] = $nodes->first();
            }
        } else {
            $data['node_id'] ??= Allocation::find($data['allocation_id'])?->node_id;
        }

        Assert::false(empty($data['node_id']), 'Expected a non-empty node_id in server creation data.');

        $eggVariableData = $this->validatorService
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle(Arr::get($data, 'egg_id'), Arr::get($data, 'environment', []));

        // Due to the design of the Daemon, we need to persist this server to the disk
        // before we can actually create it on the Daemon.
        //
        // If that connection fails out we will attempt to perform a cleanup by just
        // deleting the server itself from the system.
        /** @var Server $server */
        $server = $this->connection->transaction(function () use ($data, $eggVariableData) {
            // Create the server and assign any additional allocations to it.
            $server = $this->createModel($data);

            if ($server->allocation_id) {
                $this->storeAssignedAllocations($server, $data);
            }

            $this->storeEggVariables($server, $eggVariableData);

            return $server;
        }, 5);

        try {
            $this->daemonServerRepository
                ->setServer($server)
                ->create($data['start_on_completion'] ?? false);
        } catch (ConnectionException $exception) {
            $this->serverDeletionService->withForce()->handle($server);

            throw $exception;
        }

        return $server;
    }

    /**
     * Store the server in the database and return the model.
     *
     * @param  array<array-key, mixed>  $data
     *
     * @throws DataValidationException
     */
    private function createModel(array $data): Server
    {
        $uuid = $this->generateUniqueUuidCombo();

        return Server::create([
            'external_id' => Arr::get($data, 'external_id'),
            'uuid' => $uuid,
            'uuid_short' => substr($uuid, 0, 8),
            'node_id' => Arr::get($data, 'node_id'),
            'name' => Arr::get($data, 'name'),
            'description' => Arr::get($data, 'description') ?? '',
            'status' => ServerState::Installing,
            'skip_scripts' => Arr::get($data, 'skip_scripts') ?? isset($data['skip_scripts']),
            'owner_id' => Arr::get($data, 'owner_id'),
            'memory' => Arr::get($data, 'memory'),
            'swap' => Arr::get($data, 'swap'),
            'disk' => Arr::get($data, 'disk'),
            'io' => Arr::get($data, 'io'),
            'cpu' => Arr::get($data, 'cpu'),
            'threads' => Arr::get($data, 'threads'),
            'oom_killer' => Arr::get($data, 'oom_killer') ?? false,
            'allocation_id' => Arr::get($data, 'allocation_id'),
            'egg_id' => Arr::get($data, 'egg_id'),
            'startup' => Arr::get($data, 'startup'),
            'image' => Arr::get($data, 'image'),
            'database_limit' => Arr::get($data, 'database_limit') ?? 0,
            'allocation_limit' => Arr::get($data, 'allocation_limit') ?? 0,
            'backup_limit' => Arr::get($data, 'backup_limit') ?? 0,
            'docker_labels' => Arr::get($data, 'docker_labels'),
        ]);
    }

    /**
     * Configure the allocations assigned to this server.
     *
     * @param  array{allocation_id: int, allocation_additional?: ?int[]}  $data
     */
    private function storeAssignedAllocations(Server $server, array $data): void
    {
        $records = [$data['allocation_id']];
        if (isset($data['allocation_additional'])) {
            $records = array_merge($records, $data['allocation_additional']);
        }

        Allocation::query()
            ->whereIn('id', array_values(array_unique($records)))
            ->whereNull('server_id')
            ->lockForUpdate()
            ->get()
            ->each(function (Allocation $allocation) use ($server) {
                $allocation->server_id = $server->id;
                $allocation->is_locked = true;
                $allocation->save();
            });
    }

    /**
     * Process environment variables passed for this server and store them in the database.
     */
    private function storeEggVariables(Server $server, Collection $variables): void
    {
        foreach ($variables as $variable) {
            $server->serverVariables()->forceCreate([
                'variable_id' => $variable->id,
                'variable_value' => $variable->value ?? '',
            ]);
        }
    }

    /**
     * Create a unique UUID and UUID-Short combo for a server.
     */
    private function generateUniqueUuidCombo(): string
    {
        $uuid = Uuid::uuid4()->toString();

        $shortUuid = str($uuid)->substr(0, 8);
        if (Server::query()->where('uuid', $uuid)->orWhere('uuid_short', $shortUuid)->exists()) {
            return $this->generateUniqueUuidCombo();
        }

        return $uuid;
    }
}
