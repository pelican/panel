<?php

namespace App\Tests\Integration\Repositories\Daemon;

use App\Models\Node;
use App\Models\Server;
use App\Models\ServerTransfer;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Support\Facades\Http;

class DaemonServerRepositoryTest extends IntegrationTestCase
{
    public function test_cancel_transfer_notifies_both_nodes(): void
    {
        Http::fake();

        $oldNode = Node::factory()->make(['uuid' => 'old-node', 'fqdn' => 'old.example.com']);
        $newNode = Node::factory()->make(['uuid' => 'new-node', 'fqdn' => 'new.example.com']);
        $transfer = ServerTransfer::factory()->make()
            ->setRelation('oldNode', $oldNode)
            ->setRelation('newNode', $newNode);
        $server = Server::factory()->make(['uuid' => 'server-1234'])
            ->setRelation('node', $oldNode)
            ->setRelation('transfer', $transfer);

        app(DaemonServerRepository::class)->setServer($server)->cancelTransfer();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'old.example.com')
            && str_ends_with($request->url(), '/api/servers/server-1234/transfer'));

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'new.example.com')
            && str_ends_with($request->url(), '/api/transfers/server-1234'));
    }
}
