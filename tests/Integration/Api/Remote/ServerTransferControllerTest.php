<?php

namespace App\Tests\Integration\Api\Remote;

use App\Enums\ServerState;
use App\Models\Allocation;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Node;
use App\Models\ServerRestore;
use App\Models\ServerTransfer;
use App\Tests\Integration\IntegrationTestCase;
use Ramsey\Uuid\Uuid;

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

    public function test_successful_transfer_moves_the_transferred_backups_and_forgets_the_rest(): void
    {
        $server = $this->transfer->server;
        $host = BackupHost::factory()->create();
        $s3 = BackupHost::factory()->create(['schema' => 's3']);
        $moved = Backup::factory()->create(['server_id' => $server->id, 'node_id' => $server->node_id, 'backup_host_id' => $host->id]);
        $left = Backup::factory()->create(['server_id' => $server->id, 'node_id' => $server->node_id, 'backup_host_id' => $host->id]);
        $remote = Backup::factory()->create(['server_id' => $server->id, 'node_id' => $server->node_id, 'backup_host_id' => $s3->id]);
        $this->transfer->update(['backup_uuids' => [$moved->uuid]]);

        $newNode = $this->transfer->newNode;
        $this->withHeader('Authorization', "Bearer $newNode->daemon_token_id." . $newNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/success")
            ->assertNoContent();

        $this->assertSame($newNode->id, $moved->refresh()->node_id);
        // The old node deleted it with the server.
        $this->assertNull(Backup::query()->find($left->id));
        // Not stored on a node, so the transfer does not affect it.
        $this->assertNotNull(Backup::query()->find($remote->id));
    }

    public function test_successful_transfer_leaves_backups_held_by_another_node(): void
    {
        $server = $this->transfer->server;
        $elsewhere = Node::factory()->create();
        $backup = Backup::factory()->create(['server_id' => $server->id, 'node_id' => $elsewhere->id, 'backup_host_id' => BackupHost::factory()->create()->id]);

        $newNode = $this->transfer->newNode;
        $this->withHeader('Authorization', "Bearer $newNode->daemon_token_id." . $newNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/success")
            ->assertNoContent();

        $this->assertSame($elsewhere->id, $backup->refresh()->node_id);
    }

    public function test_successful_transfer_leaves_listed_backups_held_by_another_node(): void
    {
        $server = $this->transfer->server;
        $elsewhere = Node::factory()->create();
        $backup = Backup::factory()->create(['server_id' => $server->id, 'node_id' => $elsewhere->id, 'backup_host_id' => BackupHost::factory()->create()->id]);
        $this->transfer->update(['backup_uuids' => [$backup->uuid]]);

        $newNode = $this->transfer->newNode;
        $this->withHeader('Authorization', "Bearer $newNode->daemon_token_id." . $newNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/success")
            ->assertNoContent();

        $this->assertSame($elsewhere->id, $backup->refresh()->node_id);
    }

    public function test_a_restarting_node_fails_restores_waiting_for_an_interrupted_install(): void
    {
        $node = $this->transfer->oldNode;
        $installing = $this->createServerModel(['node_id' => $node->id, 'status' => ServerState::Installing]);
        $installed = $this->createServerModel(['node_id' => $node->id, 'status' => null]);
        $interrupted = ServerRestore::query()->create(['uuid' => Uuid::uuid4()->toString(), 'server_id' => $installing->id, 'source_url' => 'https://example.com/a.tar.gz']);
        $waiting = ServerRestore::query()->create(['uuid' => Uuid::uuid4()->toString(), 'server_id' => $installed->id, 'source_url' => 'https://example.com/a.tar.gz']);

        $this->withHeader('Authorization', "Bearer $node->daemon_token_id." . $node->daemon_token)
            ->postJson('/api/remote/servers/reset')
            ->assertNoContent();

        $this->assertSame(ServerRestore::STATUS_FAILED, $interrupted->refresh()->status);
        $this->assertSame(ServerRestore::STATUS_PENDING, $waiting->refresh()->status);
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
