<?php

namespace App\Tests\Integration\Api\Remote;

use App\Models\Allocation;
use App\Models\Node;
use App\Models\ServerTransfer;
use App\Tests\Integration\IntegrationTestCase;

class ServerTransferControllerTest extends IntegrationTestCase
{
    protected ServerTransfer $transfer;

    protected function setup(): void
    {
        parent::setUp();

        $server = $this->createServerModel();

        $new = Node::factory()
            ->has(Allocation::factory())
            ->create();

        $this->transfer = ServerTransfer::factory()->for($server)->create([
            'old_allocation' => $server->allocation_id,
            'new_allocation' => $new->allocations->first()->id,
            'new_node' => $new->id,
            'old_node' => $server->node_id,
        ]);
    }

    public function test_a_restarting_node_fails_all_of_its_unfinished_transfers(): void
    {
        $oldNode = $this->transfer->oldNode;

        ServerTransfer::factory()->for($this->transfer->server)->count(1001)->create([
            'new_node' => $this->transfer->new_node,
            'old_node' => $oldNode->id,
        ]);

        $this->withHeader('Authorization', "Bearer $oldNode->daemon_token_id." . $oldNode->daemon_token)
            ->postJson('/api/remote/servers/reset')
            ->assertNoContent();

        $this->assertSame(0, ServerTransfer::query()->whereNull('successful')->count());
    }

    public function test_success_status_update_can_be_sent_from_new_node(): void
    {
        $server = $this->transfer->server;
        $newNode = $this->transfer->newNode;

        $this->withHeader('Authorization', "Bearer $newNode->daemon_token_id." . $newNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/success")
            ->assertNoContent();

        $this->assertTrue($this->transfer->refresh()->successful);
    }

    public function test_failure_status_update_can_be_sent_from_old_node(): void
    {
        $server = $this->transfer->server;
        $oldNode = $this->transfer->oldNode;

        $this->withHeader('Authorization', "Bearer $oldNode->daemon_token_id." . $oldNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/failure")
            ->assertNoContent();

        $this->assertFalse($this->transfer->refresh()->successful);
    }

    public function test_failure_status_update_can_be_sent_from_new_node(): void
    {
        $server = $this->transfer->server;
        $newNode = $this->transfer->newNode;

        $this->withHeader('Authorization', "Bearer $newNode->daemon_token_id." . $newNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/failure")
            ->assertNoContent();

        $this->assertFalse($this->transfer->refresh()->successful);
    }

    public function test_success_status_update_cannot_be_sent_from_old_node(): void
    {
        $server = $this->transfer->server;
        $oldNode = $this->transfer->oldNode;

        $this->withHeader('Authorization', "Bearer $oldNode->daemon_token_id." . $oldNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/success")
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'HttpForbiddenException')
            ->assertJsonPath('errors.0.detail', 'Requesting node does not have permission to access this server.');

        $this->assertNull($this->transfer->refresh()->successful);
    }

    public function test_success_status_update_cannot_be_sent_from_unauthorized_node(): void
    {
        $server = $this->transfer->server;
        $node = Node::factory()->create();

        $this->withHeader('Authorization', "Bearer $node->daemon_token_id." . $node->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/transfer/success")
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'HttpForbiddenException')
            ->assertJsonPath('errors.0.detail', 'Requesting node does not have permission to access this server.');

        $this->assertNull($this->transfer->refresh()->successful);
    }

    public function test_failure_status_update_cannot_be_sent_from_unauthorized_node(): void
    {
        $server = $this->transfer->server;
        $node = Node::factory()->create();

        $this->withHeader('Authorization', "Bearer $node->daemon_token_id." . $node->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/transfer/failure")->assertForbidden()
            ->assertJsonPath('errors.0.code', 'HttpForbiddenException')
            ->assertJsonPath('errors.0.detail', 'Requesting node does not have permission to access this server.');

        $this->assertNull($this->transfer->refresh()->successful);
    }
}
