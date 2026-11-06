<?php

use App\Enums\AllocationProtocol;
use App\Models\Allocation;
use App\Models\Node;
use App\Services\Allocations\AssignmentService;
use App\Services\Servers\ServerConfigurationStructureService;
use Illuminate\Support\Facades\DB;

it('exposes allocations over both protocols by default', function () {
    $allocation = Allocation::factory()->create();

    expect($allocation->refresh()->protocol)->toBe(AllocationProtocol::Both)
        ->and(DB::table('allocations')->where('id', $allocation->id)->value('protocol'))->toBe('both');
});

it('stores the protocol of an allocation', function (AllocationProtocol $protocol) {
    $allocation = Allocation::factory()->create(['protocol' => $protocol]);

    expect($allocation->refresh()->protocol)->toBe($protocol);
})->with([AllocationProtocol::Tcp, AllocationProtocol::Udp, AllocationProtocol::Both]);

it('lists only restricted ports in the protocols of a server', function () {
    $server = createServerModel();
    $primary = $server->allocation;
    Allocation::factory()->forServer($server)->create(['ip' => $primary->ip, 'port' => 19132, 'protocol' => AllocationProtocol::Udp]);
    Allocation::factory()->forServer($server)->create(['ip' => '10.0.0.5', 'port' => 25575, 'protocol' => AllocationProtocol::Tcp]);
    Allocation::factory()->forServer($server)->create(['ip' => '10.0.0.5', 'port' => 25565]);

    expect($server->refresh()->getAllocationProtocols())->toBe([
        $primary->ip => [19132 => 'udp'],
        '10.0.0.5' => [25575 => 'tcp'],
    ]);
});

it('sends the protocols to the daemon as objects next to every mapped port', function () {
    $server = createServerModel();
    $primary = $server->allocation;
    Allocation::factory()->forServer($server)->create(['ip' => $primary->ip, 'port' => 19132, 'protocol' => AllocationProtocol::Udp]);
    Allocation::factory()->forServer($server)->create(['ip' => '10.0.0.5', 'port' => 25575, 'protocol' => AllocationProtocol::Tcp]);
    Allocation::factory()->forServer($server)->create(['ip' => '10.0.0.5', 'port' => 25565]);

    $payload = json_decode(json_encode(app(ServerConfigurationStructureService::class)->handle($server->refresh())));

    expect($payload->allocations->protocols)->toEqual((object) [
        $primary->ip => (object) ['19132' => 'udp'],
        '10.0.0.5' => (object) ['25575' => 'tcp'],
    ])
        ->and($payload->allocations->mappings->{'10.0.0.5'})->toEqualCanonicalizing([25575, 25565])
        ->and($payload->allocations->mappings->{$primary->ip})->toContain(19132);
});

it('encodes the protocols of a single restricted port as a json object', function () {
    $server = createServerModel();
    $server->allocation->update(['protocol' => AllocationProtocol::Udp]);

    $json = json_encode(app(ServerConfigurationStructureService::class)->handle($server->refresh())['allocations']['protocols']);

    expect($json)->toBe('{"' . $server->allocation->ip . '":{"' . $server->allocation->port . '":"udp"}}');
});

it('leaves the protocols out of the daemon payload when every allocation uses both', function () {
    $server = createServerModel();
    Allocation::factory()->forServer($server)->create();

    $allocations = app(ServerConfigurationStructureService::class)->handle($server->refresh())['allocations'];

    expect($allocations)->not->toHaveKey('protocols')
        ->and(json_encode($allocations))->not->toContain('protocols');
});

it('ignores restricted allocations of other nodes in the protocols', function () {
    $server = createServerModel();
    Allocation::factory()->for($server)->for(Node::factory()->create())->create(['protocol' => AllocationProtocol::Udp]);

    expect($server->refresh()->getAllocationProtocols())->toBe([]);
});

it('keeps the protocol when an allocation is released', function () {
    $server = createServerModel();
    $udp = Allocation::factory()->forServer($server)->create(['protocol' => AllocationProtocol::Udp]);

    $server->releaseAllocations([$udp->id]);

    expect($udp->refresh())
        ->server_id->toBeNull()
        ->protocol->toBe(AllocationProtocol::Udp);
});

it('keeps the protocol of an unassigned allocation when it is edited', function () {
    $allocation = Allocation::factory()->create(['protocol' => AllocationProtocol::Tcp]);

    $allocation->update(['ip_alias' => 'play.example.com']);

    expect($allocation->refresh()->protocol)->toBe(AllocationProtocol::Tcp);
});

it('creates allocations with the chosen protocol', function () {
    $node = Node::factory()->create();
    $service = app(AssignmentService::class);

    $udpIds = $service->handle($node, ['allocation_ip' => '10.0.0.1', 'allocation_ports' => ['19132-19133'], 'protocol' => 'udp']);
    $defaultIds = $service->handle($node, ['allocation_ip' => '10.0.0.1', 'allocation_ports' => ['25565']]);

    expect(Allocation::query()->whereIn('id', $udpIds)->get()->pluck('protocol')->all())->toBe([AllocationProtocol::Udp, AllocationProtocol::Udp])
        ->and(Allocation::query()->whereIn('id', $defaultIds)->get()->pluck('protocol')->all())->toBe([AllocationProtocol::Both]);
});
