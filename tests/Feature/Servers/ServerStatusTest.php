<?php

use App\Enums\ContainerStatus;
use App\Models\Node;
use Illuminate\Support\Facades\Http;

function wingsResponse(Node $node, ?array $body, int $status = 200): Closure
{
    return fn () => Http::response($body, $status, ['User-Agent' => "Pelican Wings/v1.0.0 (id:$node->daemon_token_id)"]);
}

it('returns the state the daemon reports', function () {
    $server = createServerModel();
    Http::fake(["{$server->node->fqdn}*" => wingsResponse($server->node, ['state' => 'running'])]);

    expect($server->retrieveStatus())->toBe(ContainerStatus::Running);
});

it('asks an unreachable node only once for all of its servers', function () {
    $node = Node::factory()->create();
    $servers = collect(range(1, 3))->map(fn () => createServerModel(['node_id' => $node->id]));
    Http::fake(['*' => Http::failedConnection()]);

    foreach ($servers as $server) {
        expect($server->retrieveStatus())->toBe(ContainerStatus::Missing);
    }

    Http::assertSentCount(1);
    expect($node->isUnreachable())->toBeTrue();
});

it('keeps asking other nodes while one is unreachable', function () {
    $down = createServerModel();
    $up = createServerModel();
    Http::fake([
        "{$down->node->fqdn}*" => Http::failedConnection(),
        "{$up->node->fqdn}*" => wingsResponse($up->node, ['state' => 'offline']),
    ]);

    expect($down->retrieveStatus())->toBe(ContainerStatus::Missing)
        ->and($up->retrieveStatus())->toBe(ContainerStatus::Offline);
    Http::assertSentCount(2);
});

it('does not mark a node unreachable when it only lacks the server', function () {
    $node = Node::factory()->create();
    [$missing, $other] = [createServerModel(['node_id' => $node->id]), createServerModel(['node_id' => $node->id])];
    Http::fake(["$node->fqdn*" => Http::sequence()
        ->pushResponse(Http::response(null, 404, ['User-Agent' => "Pelican Wings/v1.0.0 (id:$node->daemon_token_id)"]))
        ->pushResponse(Http::response(['state' => 'running'], 200, ['User-Agent' => "Pelican Wings/v1.0.0 (id:$node->daemon_token_id)"])),
    ]);

    expect($missing->retrieveStatus())->toBe(ContainerStatus::Missing)
        ->and($node->isUnreachable())->toBeFalse()
        ->and($other->retrieveStatus())->toBe(ContainerStatus::Running);
});
