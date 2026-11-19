<?php

namespace App\Services\Allocations;

use App\Enums\AllocationProtocol;
use App\Exceptions\DisplayException;
use App\Exceptions\Service\Allocation\CidrOutOfRangeException;
use App\Exceptions\Service\Allocation\InvalidPortMappingException;
use App\Exceptions\Service\Allocation\PortOutOfRangeException;
use App\Exceptions\Service\Allocation\TooManyPortsInRangeException;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use IPTools\Network;
use Throwable;

class AssignmentService
{
    public const CIDR_MAX_BITS = 25;

    public const CIDR_MIN_BITS = 32;

    public const PORT_FLOOR = 1024;

    public const PORT_CEIL = 65535;

    public const PORT_RANGE_LIMIT = 1000;

    public const PORT_RANGE_REGEX = '/^(\d{4,5})-(\d{4,5})$/';

    public function __construct(protected ConnectionInterface $connection) {}

    /**
     * Insert allocations into the database and link them to a specific node.
     *
     * @param  array{allocation_ip: string, allocation_ports: array<int|string>, allocation_alias?: string|null, is_locked?: bool, protocol?: AllocationProtocol|string|null}  $data
     * @return array<int>
     *
     * @throws DisplayException
     * @throws CidrOutOfRangeException
     * @throws InvalidPortMappingException
     * @throws PortOutOfRangeException
     * @throws TooManyPortsInRangeException
     */
    public function handle(Node $node, array $data, ?Server $server = null): array
    {
        $result = $this->assign($node, $data, $server);

        return $result['ids'];
    }

    /**
     * @param  array{allocation_ip: string, allocation_ports: array<int|string>, allocation_alias?: string|null, is_locked?: bool, protocol?: AllocationProtocol|string|null}  $data
     * @return array{created: array<int>, merged: array<int>, ids: array<int>}
     *
     * @throws DisplayException
     * @throws CidrOutOfRangeException
     * @throws InvalidPortMappingException
     * @throws PortOutOfRangeException
     * @throws TooManyPortsInRangeException
     */
    public function assign(Node $node, array $data, ?Server $server = null): array
    {
        $explode = explode('/', $data['allocation_ip']);
        if (count($explode) !== 1) {
            throw_if(!ctype_digit($explode[1]) || ($explode[1] > self::CIDR_MIN_BITS || $explode[1] < self::CIDR_MAX_BITS), new CidrOutOfRangeException());
        }

        try {
            $parsed = Network::parse($data['allocation_ip']);
        } catch (Exception $exception) {
            throw new DisplayException("Could not parse provided allocation IP address ({$data['allocation_ip']}): {$exception->getMessage()}", $exception);
        }

        $ports = $this->parsePorts($data['allocation_ports']);
        $requested = $data['protocol'] ?? null;
        $protocol = $requested instanceof AllocationProtocol ? $requested : (AllocationProtocol::tryFrom($requested ?? '') ?? AllocationProtocol::Both);

        $created = $merged = $ids = [];

        $this->connection->beginTransaction();

        try {
            foreach ($parsed as $ip) {
                $result = $this->assignToIp($node, $ip->__toString(), $ports, $protocol, $data, $server);

                $created = array_merge($created, $result['created']);
                $merged = array_merge($merged, $result['merged']);
                $ids = array_merge($ids, $result['ids']);
            }

            if ($server && !$server->allocation_id) {
                $server->update(['allocation_id' => $ids[0]]);
            }

            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }

        return ['created' => $created, 'merged' => $merged, 'ids' => $ids];
    }

    /**
     * @param  array<int>  $ports
     * @param  array{allocation_ip: string, allocation_ports: array<int|string>, allocation_alias?: string|null, is_locked?: bool, protocol?: AllocationProtocol|string|null}  $data
     * @return array{created: array<int>, merged: array<int>, ids: array<int>}
     *
     * @throws DisplayException
     */
    private function assignToIp(Node $node, string $ip, array $ports, AllocationProtocol $protocol, array $data, ?Server $server): array
    {
        $existing = $this->findExisting($node, $ip, $ports);

        $created = $merged = $ids = [];

        foreach ($ports as $port) {
            $current = $existing->get($port);

            if ($current && $this->canMerge($current, $protocol)) {
                $merged[] = $ids[] = $current->id;

                continue;
            }

            $allocation = Allocation::query()->create([
                'node_id' => $node->id,
                'ip' => $ip,
                'port' => $port,
                'protocol' => $protocol->value,
                'ip_alias' => array_get($data, 'allocation_alias'),
                'server_id' => $server->id ?? null,
                'is_locked' => array_get($data, 'is_locked', false),
            ]);
            $created[] = $ids[] = $allocation->id;
        }

        $merged = array_values(array_unique($merged));
        $this->merge($merged, $data, $server);

        return ['created' => $created, 'merged' => $merged, 'ids' => array_values(array_unique($ids))];
    }

    /**
     * @param  array<int>  $ports
     * @return Collection<int, Allocation>
     */
    private function findExisting(Node $node, string $ip, array $ports): Collection
    {
        $existing = collect();

        foreach (array_chunk($ports, 500) as $chunk) {
            $existing = $existing->concat(
                Allocation::withoutGlobalScopes()->where('node_id', $node->id)->where('ip', $ip)->whereIn('port', $chunk)->lockForUpdate()->get(['id', 'port', 'protocol', 'server_id'])
            );
        }

        return $existing->keyBy('port');
    }

    /**
     * @param  array<int>  $ids
     * @param  array{allocation_ip: string, allocation_ports: array<int|string>, allocation_alias?: string|null, is_locked?: bool, protocol?: AllocationProtocol|string|null}  $data
     *
     * @throws DisplayException
     */
    private function merge(array $ids, array $data, ?Server $server): void
    {
        if (empty($ids)) {
            return;
        }

        $attributes = ['protocol' => AllocationProtocol::Both->value];
        if ($server) {
            $attributes += ['server_id' => $server->id, 'is_locked' => array_get($data, 'is_locked', false)];
        }

        $updated = Allocation::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->whereNull('server_id')
            ->where('protocol', '!=', AllocationProtocol::Both->value)
            ->update($attributes);

        throw_if($updated !== count($ids), new DisplayException(trans('exceptions.allocations.merge_conflict')));
    }

    private function canMerge(Allocation $existing, AllocationProtocol $requested): bool
    {
        return $existing->server_id === null
            && $existing->protocol !== AllocationProtocol::Both
            && $existing->protocol !== $requested;
    }

    /**
     * @param  array<int|string>  $entries
     * @return array<int>
     *
     * @throws InvalidPortMappingException
     * @throws PortOutOfRangeException
     * @throws TooManyPortsInRangeException
     */
    private function parsePorts(array $entries): array
    {
        $ports = [];

        foreach ($entries as $port) {
            throw_if(!is_digit($port) && !preg_match(self::PORT_RANGE_REGEX, $port), new InvalidPortMappingException($port));

            if (preg_match(self::PORT_RANGE_REGEX, $port, $matches)) {
                $block = range($matches[1], $matches[2]);

                throw_if(count($block) > self::PORT_RANGE_LIMIT, new TooManyPortsInRangeException());

                throw_if((int) $matches[1] < self::PORT_FLOOR || (int) $matches[2] > self::PORT_CEIL, new PortOutOfRangeException());

                foreach ($block as $unit) {
                    $ports[] = (int) $unit;
                }
            } else {
                throw_if((int) $port < self::PORT_FLOOR || (int) $port > self::PORT_CEIL, new PortOutOfRangeException());

                $ports[] = (int) $port;
            }
        }

        return $ports;
    }
}
