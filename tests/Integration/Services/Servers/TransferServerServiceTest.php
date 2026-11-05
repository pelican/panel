<?php

namespace App\Tests\Integration\Services\Servers;

use App\Exceptions\Http\Server\NodeNotViableException;
use App\Exceptions\Service\Allocation\AllocationUnavailableException;
use App\Exceptions\Service\Server\TransferUnconfirmedException;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerTransfer;
use App\Services\Servers\TransferServerService;
use App\Tests\Integration\IntegrationTestCase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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

    public function test_transfer_is_refused_when_an_allocation_belongs_to_another_server(): void
    {
        Http::fake();

        $server = $this->createServerModel();
        $target = Node::factory()->create();
        $other = $this->createServerModel(['node_id' => $target->id]);
        $free = Allocation::factory()->create(['node_id' => $target->id]);
        $taken = Allocation::factory()->create(['node_id' => $target->id, 'server_id' => $other->id]);

        try {
            $this->getService()->handle($server, $target->id, $free->id, [$taken->id]);
            $this->fail('The transfer should have been refused.');
        } catch (AllocationUnavailableException) {
            $this->assertSame(0, ServerTransfer::query()->count());
            $this->assertNull($free->refresh()->server_id);
            $this->assertSame($other->id, $taken->refresh()->server_id);
            Http::assertNothingSent();
        }
    }

    public function test_transfer_is_refused_for_an_allocation_on_another_node(): void
    {
        Http::fake();

        $server = $this->createServerModel();
        $target = Node::factory()->create();
        $elsewhere = Allocation::factory()->create(['node_id' => Node::factory()->create()->id]);

        $this->expectException(AllocationUnavailableException::class);

        try {
            $this->getService()->handle($server, $target->id, $elsewhere->id);
        } finally {
            $this->assertSame(0, ServerTransfer::query()->count());
            $this->assertNull($elsewhere->refresh()->server_id);
        }
    }

    public function test_transfer_stays_open_when_the_node_took_the_request_but_never_answered(): void
    {
        [$server, $target, $allocation] = $this->transferThatFailsWith(new NetworkTimeoutException('timed out', new Request('POST', 'http://node')));

        try {
            $this->getService()->handle($server, $target->id, $allocation->id);
            $this->fail('The transfer should not count as started.');
        } catch (TransferUnconfirmedException $exception) {
            $this->assertTrue($exception->transfer->is($server->refresh()->transfer));
            $this->assertNull($exception->transfer->refresh()->successful);
            $this->assertSame($server->id, $allocation->refresh()->server_id);
        }
    }

    public function test_transfer_fails_and_frees_its_ports_when_the_node_was_never_reached(): void
    {
        [$server, $target, $allocation] = $this->transferThatFailsWith(new ConnectException('refused', new Request('POST', 'http://node')));

        try {
            $this->getService()->handle($server, $target->id, $allocation->id);
            $this->fail('The transfer should not count as started.');
        } catch (ConnectionException) {
            $this->assertFalse(ServerTransfer::query()->sole()->successful);
            $this->assertNull($allocation->refresh()->server_id);
        }
    }

    /** @return array{Server, Node, Allocation} */
    private function transferThatFailsWith(TransferException $cause): array
    {
        Http::fake(['*/transfer' => fn () => throw new ConnectionException($cause->getMessage(), 0, $cause)]);

        $target = Node::factory()->create();

        return [$this->createServerModel(), $target, Allocation::factory()->create(['node_id' => $target->id])];
    }

    private function getService(): TransferServerService
    {
        return $this->app->make(TransferServerService::class);
    }
}
