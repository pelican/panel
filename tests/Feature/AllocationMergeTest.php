<?php

use App\Enums\AllocationProtocol;
use App\Filament\Admin\Resources\Servers\Pages\CreateServer;
use App\Models\Allocation;
use App\Models\Node;
use App\Services\Allocations\AssignmentService;
use Illuminate\Database\QueryException;

function assignPorts(Node $node, array $ports, string $protocol, string $ip = '10.0.0.1', $server = null): array
{
    return app(AssignmentService::class)->assign($node, ['allocation_ip' => $ip, 'allocation_ports' => $ports, 'protocol' => $protocol], $server);
}

function protocolsOf(Node $node, string $ip = '10.0.0.1'): array
{
    return Allocation::query()->where('node_id', $node->id)->where('ip', $ip)->orderBy('port')->get()
        ->mapWithKeys(fn (Allocation $allocation) => [$allocation->port => $allocation->protocol->value])->all();
}

it('merges a udp allocation onto an existing tcp one', function () {
    $node = Node::factory()->create();

    $first = assignPorts($node, ['25565'], 'tcp');
    $second = assignPorts($node, ['25565'], 'udp');

    expect($second['created'])->toBe([])
        ->and($second['merged'])->toBe($first['created'])
        ->and(protocolsOf($node))->toBe([25565 => 'both'])
        ->and(Allocation::query()->where('node_id', $node->id)->count())->toBe(1);
});

it('merges a tcp allocation onto an existing udp one', function () {
    $node = Node::factory()->create();

    assignPorts($node, ['19132'], 'udp');
    assignPorts($node, ['19132'], 'tcp');

    expect(protocolsOf($node))->toBe([19132 => 'both']);
});

it('keeps failing on the same protocol like it always did', function (string $protocol) {
    $node = Node::factory()->create();
    assignPorts($node, ['25565'], $protocol);

    try {
        assignPorts($node, ['25565'], $protocol);
        $this->fail('Expected a unique violation.');
    } catch (QueryException) {
        expect(protocolsOf($node))->toBe([25565 => $protocol]);
    }
})->with(['tcp', 'udp', 'both']);

it('does not change an allocation that already uses both', function (string $protocol) {
    $node = Node::factory()->create();
    assignPorts($node, ['25565'], 'both');

    expect(fn () => assignPorts($node, ['25565'], $protocol))->toThrow(QueryException::class)
        ->and(protocolsOf($node))->toBe([25565 => 'both']);
})->with(['tcp', 'udp']);

it('does not widen an allocation that is assigned to a server', function () {
    $server = createServerModel();
    $server->allocation->update(['ip' => '10.0.0.1', 'port' => 25565, 'protocol' => AllocationProtocol::Tcp]);

    expect(fn () => assignPorts($server->node, ['25565'], 'udp'))->toThrow(QueryException::class)
        ->and($server->allocation->refresh()->protocol)->toBe(AllocationProtocol::Tcp);
});

it('only merges the clashing ports of a range and creates the rest', function () {
    $node = Node::factory()->create();
    assignPorts($node, ['30001', '30003', '30005'], 'tcp');
    assignPorts($node, ['30004'], 'both');

    $result = assignPorts($node, ['30001-30003'], 'udp');

    expect($result['created'])->toHaveCount(1)
        ->and($result['merged'])->toHaveCount(2)
        ->and(protocolsOf($node))->toBe([30001 => 'both', 30002 => 'udp', 30003 => 'both', 30004 => 'both', 30005 => 'tcp']);
});

it('rolls everything back when a range runs into an allocation that cannot be merged', function () {
    $node = Node::factory()->create();
    assignPorts($node, ['30001'], 'tcp');
    assignPorts($node, ['30003'], 'udp');

    expect(fn () => assignPorts($node, ['30001-30003'], 'udp'))->toThrow(QueryException::class)
        ->and(protocolsOf($node))->toBe([30001 => 'tcp', 30003 => 'udp']);
});

it('merges on every ip of a cidr', function () {
    $node = Node::factory()->create();
    assignPorts($node, ['25565'], 'tcp', '10.0.0.0/30');

    $result = assignPorts($node, ['25565'], 'udp', '10.0.0.0/30');

    expect($result['created'])->toBe([])
        ->and($result['merged'])->toHaveCount(4)
        ->and(Allocation::query()->where('node_id', $node->id)->where('protocol', 'both')->count())->toBe(4);
});

it('does not merge allocations of another ip or node', function () {
    $node = Node::factory()->create();
    assignPorts($node, ['25565'], 'tcp', '10.0.0.1');
    assignPorts(Node::factory()->create(), ['25565'], 'tcp', '10.0.0.1');

    $result = assignPorts($node, ['25565'], 'udp', '10.0.0.2');

    expect($result['merged'])->toBe([])
        ->and($result['created'])->toHaveCount(1)
        ->and(protocolsOf($node, '10.0.0.1'))->toBe([25565 => 'tcp']);
});

it('attaches a merged allocation to the server it was requested for', function () {
    $server = createServerModel();
    $node = $server->node;
    assignPorts($node, ['25565'], 'tcp');

    $result = assignPorts($node, ['25565'], 'udp', '10.0.0.1', $server);

    expect($result['merged'])->toHaveCount(1)
        ->and(Allocation::query()->findOrFail($result['merged'][0]))
        ->server_id->toBe($server->id)
        ->protocol->toBe(AllocationProtocol::Both);
});

it('still returns the ids of created and merged allocations from handle', function () {
    $node = Node::factory()->create();
    $tcp = assignPorts($node, ['30000'], 'tcp')['created'];

    $ids = app(AssignmentService::class)->handle($node, ['allocation_ip' => '10.0.0.1', 'allocation_ports' => ['30000-30001'], 'protocol' => 'udp']);

    expect($ids)->toHaveCount(2)->and($ids)->toContain($tcp[0]);
});

it('keeps ports that can be merged when validating the ports of the create form', function () {
    $node = Node::factory()->create();
    assignPorts($node, ['30001'], 'tcp');
    assignPorts($node, ['30002'], 'both');
    assignPorts($node, ['30003'], 'udp');
    $server = createServerModel(['node_id' => $node->id]);
    $server->allocation->update(['ip' => '10.0.0.1', 'port' => 30004, 'protocol' => AllocationProtocol::Tcp]);

    $valid = fn (?string $protocol) => array_values(CreateServer::retrieveValidPorts($node, ['30001-30005'], '10.0.0.1', $protocol));

    expect($valid('udp'))->toBe([30001, 30005])
        ->and($valid('tcp'))->toBe([30003, 30005])
        ->and($valid('both'))->toBe([30001, 30003, 30005])
        ->and($valid(null))->toBe([30005])
        ->and($valid('invalid'))->toBe([30005]);
});
