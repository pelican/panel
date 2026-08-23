<?php

namespace App\Tests\Integration\Services\Servers;

use App\Exceptions\Http\Server\NodeNotViableException;
use App\Models\Node;
use App\Models\ServerTransfer;
use App\Services\Servers\TransferServerService;
use App\Tests\Integration\IntegrationTestCase;

class TransferServerServiceTest extends IntegrationTestCase
{
    /**
     * A node without the resources to take the server must abort the transfer loudly.
     * It used to return false, which callers silently treated as success.
     */
    public function test_transfer_to_a_node_without_capacity_throws(): void
    {
        $server = $this->createServerModel(['memory' => 512]);

        /** @var Node $target */
        $target = Node::factory()->create([
            'memory' => 128,
            'memory_overallocate' => 0,
        ]);

        $this->expectException(NodeNotViableException::class);

        try {
            $this->getService()->handle($server, $target->id);
        } finally {
            $this->assertSame(0, ServerTransfer::query()->count());
            $this->assertSame($server->node_id, $server->refresh()->node_id);
        }
    }

    private function getService(): TransferServerService
    {
        return $this->app->make(TransferServerService::class);
    }
}
