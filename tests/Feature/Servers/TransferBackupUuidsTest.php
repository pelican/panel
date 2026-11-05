<?php

use App\Models\Allocation;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Node;
use App\Models\ServerTransfer;
use App\Services\Servers\TransferServerService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('stores the selected backup uuids on the transfer and forwards them to wings', function () {
    Http::fake();

    $server = createServerModel();
    $target = Node::factory()->create();
    $allocation = Allocation::factory()->create(['node_id' => $target->id]);
    $host = BackupHost::factory()->create();
    $backups = Backup::factory()->count(2)->create(['server_id' => $server->id, 'backup_host_id' => $host->id]);
    $selected = [$backups[0]->uuid];

    app(TransferServerService::class)->handle($server, $target->id, $allocation->id, backup_uuids: $selected);

    expect(ServerTransfer::query()->sole()->backup_uuids)->toBe($selected);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), "/api/servers/{$server->uuid}/transfer")
        && $request['backups'] === $selected);
});
