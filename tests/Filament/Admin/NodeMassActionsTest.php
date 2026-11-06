<?php

use App\Enums\RolePermissionModels;
use App\Enums\ServerState;
use App\Enums\ServerTransferBatchItemStatus;
use App\Enums\ServerTransferBatchStatus;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Nodes\Pages\ViewNode;
use App\Filament\Admin\Resources\Nodes\RelationManagers\ServerTransferBatchesRelationManager;
use App\Jobs\AdvanceServerTransferBatch;
use App\Jobs\SendServerPowerAction;
use App\Livewire\NodeMassActionTable;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\ServerTransfer;
use App\Models\ServerTransferBatch;
use App\Services\Servers\ServerTransferBatchService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

afterEach(fn () => Filament::setCurrentPanel(null));

function massActionServer(Node $node, string $name, int $port): Server
{
    $allocation = Allocation::factory()->create(['node_id' => $node->id, 'port' => $port]);
    $server = createServerModel(['node_id' => $node->id, 'allocation_id' => $allocation->id, 'name' => $name]);
    $allocation->update(['server_id' => $server->id]);

    return $server->refresh();
}

/** @param array<string, string> $states */
function fakeNodeStates(array $states): void
{
    Http::fake(['*/api/servers' => Http::response(collect($states)->map(fn ($state, $uuid) => [
        'state' => $state,
        'configuration' => ['uuid' => $uuid],
    ])->values()->all())]);
}

/** A user remembers their node restrictions, so this signs them in afresh like the next request would. */
function restrictAdminTo(Node $node): void
{
    test()->admin->roles->first()->nodes()->sync([$node->id]);
    test()->actingAs(test()->admin->fresh());
}

beforeEach(function () {
    Bus::fake([AdvanceServerTransferBatch::class, SendServerPowerAction::class]);

    $role = Role::factory()->create(['name' => 'Node Manager', 'guard_name' => 'web']);
    foreach ([
        RolePermissionModels::Node->viewAny(),
        RolePermissionModels::Node->view(),
        RolePermissionModels::Node->update(),
        RolePermissionModels::Server->update(),
    ] as $ability) {
        $role->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }

    [$this->admin] = generateTestAccount([]);
    $this->admin->syncRoles($role);
    $this->actingAs($this->admin);

    $this->node = Node::factory()->create();
});

it('does not offer mass actions on the view page', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(ViewNode::class, ['record' => $this->node->getKey()])
        ->assertDontSee(trans('admin/node.tabs.mass_actions'));
});

it('offers every mass action on the edit page', function (string $action) {
    fakeNodeStates([]);
    Node::factory()->create();

    livewire(EditNode::class, ['record' => $this->node->getKey()])
        ->assertActionVisible(TestAction::make("exclude_{$action}Servers")->schemaComponent(true));
})->with(['start', 'stop', 'restart', 'transfer']);

it('refuses admins who cannot update servers', function () {
    $this->admin->syncRoles([]);
    fakeNodeStates([]);

    livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'stop'])->assertForbidden();
});

it('refuses to act once access to the node is revoked', function (string $action) {
    $server = massActionServer($this->node, 'server', 25565);
    fakeNodeStates([$server->uuid => 'running']);

    $component = livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => $action])
        ->set('selected', [(string) $server->id]);

    restrictAdminTo(Node::factory()->create());

    $component->call('submit')->assertForbidden();

    Bus::assertNotDispatched(SendServerPowerAction::class);
    expect(ServerTransferBatch::query()->count())->toBe(0);
})->with(['stop', 'transfer']);

it('stops listing the servers of a node once access to it is revoked', function () {
    $server = massActionServer($this->node, 'server', 25565);
    fakeNodeStates([$server->uuid => 'running']);

    $component = livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'stop']);

    restrictAdminTo(Node::factory()->create());

    $component->call('$refresh')->assertForbidden();
});

it('does not offer cancelling a batch once access to the node is revoked', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    fakeNodeStates([]);
    $target = Node::factory()->create();
    Allocation::factory()->create(['node_id' => $target->id, 'ip' => '10.0.0.5', 'port' => 1]);
    $server = massActionServer($this->node, 'server', 25565);
    $batch = app(ServerTransferBatchService::class)->create($this->node, $target, [$server->id => ['ip' => '10.0.0.5', 'move_backups' => true]]);

    $component = livewire(ServerTransferBatchesRelationManager::class, ['ownerRecord' => $this->node, 'pageClass' => EditNode::class])
        ->assertActionVisible(TestAction::make('cancel')->table($batch));

    restrictAdminTo($target);

    $component->assertActionHidden(TestAction::make('cancel')->table($batch));
    expect($batch->refresh()->status)->toBe(ServerTransferBatchStatus::Running);
});

it('does not queue a server that stopped being eligible while the dialog was open', function () {
    $target = Node::factory()->create();
    Allocation::factory()->create(['node_id' => $target->id, 'ip' => '10.0.0.5', 'port' => 1]);
    $staying = massActionServer($this->node, 'staying', 25565);
    $changed = massActionServer($this->node, 'changed', 25566);
    fakeNodeStates([]);

    $component = livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'transfer'])
        ->set('data.node_id', $target->id)
        ->set('data.default_ip', '10.0.0.5')
        ->set('selected', [(string) $staying->id, (string) $changed->id]);

    $changed->update(['status' => ServerState::RestoringBackup]);

    $component->call('submit')
        ->assertDispatched('mass-action-close')
        ->assertNotified(trans_choice('admin/node.transfer_batch.notifications.started', 1, ['node' => $target->name]));

    expect(ServerTransferBatch::query()->sole()->items()->pluck('server_id')->all())->toBe([$staying->id]);
});

it('shows a port as in use when two selected servers need it', function () {
    $target = Node::factory()->create();
    Allocation::factory()->create(['node_id' => $target->id, 'ip' => '10.0.0.5', 'port' => 1]);
    $first = massActionServer($this->node, 'first', 25565);
    $second = massActionServer($this->node, 'second', 25565);
    fakeNodeStates([]);

    livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'transfer'])
        ->set('data.node_id', $target->id)
        ->set('data.default_ip', '10.0.0.5')
        ->set('selected', [(string) $first->id, (string) $second->id])
        ->assertSee('10.0.0.5:25565 (' . trans('admin/node.transfer_batch.port_taken') . ')');
});

it('does not show the IPs and ports of a target node the admin cannot access', function () {
    $hidden = Node::factory()->create();
    $occupant = massActionServer($hidden, 'occupant', 30000);
    Allocation::factory()->create(['node_id' => $hidden->id, 'ip' => '10.9.9.9', 'port' => 25565, 'server_id' => $occupant->id]);
    $server = massActionServer($this->node, 'server', 25565);
    fakeNodeStates([]);

    $select = fn () => livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'transfer'])
        ->set('selected', [(string) $server->id])
        ->set('data.node_id', $hidden->id);

    $select()->assertSee('10.9.9.9');

    restrictAdminTo($this->node);

    $select()->assertDontSee('10.9.9.9');
    $select()->set('data.default_ip', '10.9.9.8')
        ->assertDontSee('10.9.9.8:25565')
        ->assertDontSee(trans_choice('admin/node.transfer_batch.conflict_count', 1));
});

it('only lets a signal reach servers in a state it applies to', function (string $signal, string $eligibleState) {
    $eligible = massActionServer($this->node, 'eligible', 25565);
    $other = massActionServer($this->node, 'other', 25566);
    $missing = massActionServer($this->node, 'missing', 25567);
    $elsewhere = massActionServer(Node::factory()->create(), 'elsewhere', 25568);

    $otherState = $eligibleState === 'offline' ? 'running' : 'offline';
    fakeNodeStates([$eligible->uuid => $eligibleState, $other->uuid => $otherState]);

    $component = livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => $signal]);

    expect($component->get('eligible'))->toBe([$eligible->id]);

    $component->set('selected', [(string) $eligible->id, (string) $other->id, (string) $missing->id, (string) $elsewhere->id])
        ->assertSee(trans_choice('admin/node.mass_actions.selected_count', 1))
        ->call('submit');

    Bus::assertDispatchedTimes(SendServerPowerAction::class, 1);
    Bus::assertDispatched(SendServerPowerAction::class, fn ($job) => $job->server->is($eligible) && $job->signal === $signal);
})->with([
    ['start', 'offline'],
    ['stop', 'running'],
    ['restart', 'running'],
]);

it('does not send a signal to a server whose state changed after opening', function () {
    $server = massActionServer($this->node, 'server', 25565);
    Http::fake(['*/api/servers' => Http::sequence()
        ->push([['state' => 'running', 'configuration' => ['uuid' => $server->uuid]]])
        ->push([['state' => 'offline', 'configuration' => ['uuid' => $server->uuid]]])]);

    livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'stop'])
        ->set('selected', [(string) $server->id])
        ->call('submit');

    Bus::assertNotDispatched(SendServerPowerAction::class);
});

it('only offers the eligible servers for selecting all', function () {
    $eligible = massActionServer($this->node, 'eligible', 25565);
    $hidden = massActionServer($this->node, 'hidden', 25566);
    $other = massActionServer($this->node, 'other', 25567);
    fakeNodeStates([$eligible->uuid => 'running', $hidden->uuid => 'running', $other->uuid => 'offline']);

    $component = livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'stop']);

    expect($component->instance()->getAllSelectableTableRecordKeys())->toEqualCanonicalizing([(string) $eligible->id, (string) $hidden->id])
        ->and($component->instance()->getAllSelectableTableRecordsCount())->toBe(2);

    $component->searchTable('eligible');

    expect($component->instance()->getAllSelectableTableRecordKeys())->toBe([(string) $eligible->id])
        ->and($component->instance()->getAllSelectableTableRecordsCount())->toBe(1);
});

it('finds servers by name, IP and port and keeps the selection while searching', function () {
    $alpha = massActionServer($this->node, 'alpha', 25565);
    $beta = massActionServer($this->node, 'beta', 25566);
    fakeNodeStates([$alpha->uuid => 'running', $beta->uuid => 'running']);

    $component = livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'stop'])
        ->set('selected', [(string) $alpha->id])
        ->searchTable('beta')
        ->assertCanSeeTableRecords([$beta])
        ->assertCanNotSeeTableRecords([$alpha])
        ->searchTable('25565')
        ->assertCanSeeTableRecords([$alpha])
        ->assertCanNotSeeTableRecords([$beta]);

    expect($component->get('selected'))->toBe([(string) $alpha->id]);
});

it('only lists what is selected with the selected filter', function () {
    $alpha = massActionServer($this->node, 'alpha', 25565);
    $beta = massActionServer($this->node, 'beta', 25566);
    fakeNodeStates([$alpha->uuid => 'running', $beta->uuid => 'running']);

    livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'stop'])
        ->set('selected', [(string) $alpha->id])
        ->filterTable('only_selected')
        ->assertCanSeeTableRecords([$alpha])
        ->assertCanNotSeeTableRecords([$beta]);
});

it('queues the selected servers for transfer on the chosen IP', function () {
    $target = Node::factory()->create();
    Allocation::factory()->create(['node_id' => $target->id, 'ip' => '10.0.0.5', 'port' => 1]);
    Allocation::factory()->create(['node_id' => $target->id, 'ip' => '10.0.0.6', 'port' => 1]);
    $included = massActionServer($this->node, 'included', 25565);
    $override = massActionServer($this->node, 'override', 25566);
    $excluded = massActionServer($this->node, 'excluded', 25567);
    fakeNodeStates([]);

    livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'transfer'])
        ->set('data.node_id', $target->id)
        ->set('data.default_ip', '10.0.0.5')
        ->set('selected', [(string) $included->id, (string) $override->id])
        ->call('updateTableColumnState', 'ip', (string) $override->id, '10.0.0.6')
        ->call('updateTableColumnState', 'move_backups', (string) $override->id, false)
        ->call('submit')
        ->assertDispatched('mass-action-close');

    $batch = ServerTransferBatch::query()->sole();
    $items = $batch->items->keyBy('server_id');

    expect($batch->new_node)->toBe($target->id)
        ->and($items)->toHaveCount(2)
        ->and($items->has($excluded->id))->toBeFalse()
        ->and($items[$included->id]->new_ip)->toBe('10.0.0.5')
        ->and($items[$included->id]->move_backups)->toBeTrue()
        ->and($items[$included->id]->status)->toBe(ServerTransferBatchItemStatus::Queued)
        ->and($items[$override->id]->new_ip)->toBe('10.0.0.6')
        ->and($items[$override->id]->move_backups)->toBeFalse();
});

it('requires a target node', function () {
    $server = massActionServer($this->node, 'server', 25565);
    fakeNodeStates([]);

    livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'transfer'])
        ->set('selected', [(string) $server->id])
        ->call('submit')
        ->assertHasErrors(['data.node_id' => 'required']);

    expect(ServerTransferBatch::query()->count())->toBe(0);
});

it('stays open, creates nothing and shows the conflicts when a port is in use', function () {
    $target = Node::factory()->create();
    $occupant = massActionServer($target, 'occupant', 30000);
    Allocation::factory()->create(['node_id' => $target->id, 'ip' => '10.0.0.5', 'port' => 25565, 'server_id' => $occupant->id]);
    $server = massActionServer($this->node, 'server', 25565);
    $fine = massActionServer($this->node, 'fine', 25566);
    fakeNodeStates([]);

    $component = livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'transfer'])
        ->set('data.node_id', $target->id)
        ->set('data.default_ip', '10.0.0.5')
        ->set('selected', [(string) $server->id, (string) $fine->id])
        ->assertSee(trans_choice('admin/node.transfer_batch.conflict_count', 1))
        ->call('submit')
        ->assertNotDispatched('mass-action-close')
        ->assertCanSeeTableRecords([$server])
        ->assertCanNotSeeTableRecords([$fine]);

    expect(ServerTransferBatch::query()->count())->toBe(0)
        ->and($component->get('selected'))->toHaveCount(2);
});

it('shows a port as in use when the target listens on it on all IPv4 addresses', function () {
    $target = Node::factory()->create();
    $occupant = massActionServer($target, 'occupant', 30000);
    Allocation::factory()->create(['node_id' => $target->id, 'ip' => '0.0.0.0', 'port' => 25565, 'server_id' => $occupant->id]);
    Allocation::factory()->create(['node_id' => $target->id, 'ip' => '10.0.0.5', 'port' => 1]);
    $server = massActionServer($this->node, 'server', 25565);
    fakeNodeStates([]);

    livewire(NodeMassActionTable::class, ['node' => $this->node, 'action' => 'transfer'])
        ->set('data.node_id', $target->id)
        ->set('data.default_ip', '10.0.0.5')
        ->set('selected', [(string) $server->id])
        ->assertSee('10.0.0.5:25565 (' . trans('admin/node.transfer_batch.port_taken') . ')')
        ->assertSee(trans_choice('admin/node.transfer_batch.conflict_count', 1));
});

it('tells the admin when a batch could not be cancelled yet', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    fakeNodeStates([]);
    $target = Node::factory()->create();
    Allocation::factory()->create(['node_id' => $target->id, 'ip' => '10.0.0.5', 'port' => 1]);
    $server = massActionServer($this->node, 'server', 25565);
    $batch = app(ServerTransferBatchService::class)->create($this->node, $target, [$server->id => ['ip' => '10.0.0.5', 'move_backups' => true]]);

    $lock = Cache::lock("server-transfer-batch:$batch->id", 120);
    expect($lock->get())->toBeTrue();
    Sleep::fake(syncWithCarbon: true);

    livewire(ServerTransferBatchesRelationManager::class, ['ownerRecord' => $this->node, 'pageClass' => EditNode::class])
        ->callAction(TestAction::make('cancel')->table($batch))
        ->assertNotified(trans('admin/node.transfer_batch.notifications.cancel_busy'));

    expect($batch->refresh()->status)->toBe(ServerTransferBatchStatus::Running);

    $lock->release();
    $batch->items()->update([
        'status' => ServerTransferBatchItemStatus::Transferring,
        'server_transfer_id' => ServerTransfer::factory()->for($server)->create(['old_node' => $this->node->id, 'new_node' => $target->id])->id,
    ]);
    Http::fake(['*/transfer' => fn () => throw new ConnectionException('timed out', 0, new NetworkTimeoutException('timed out', new Request('DELETE', 'http://node')))]);

    livewire(ServerTransferBatchesRelationManager::class, ['ownerRecord' => $this->node, 'pageClass' => EditNode::class])
        ->callAction(TestAction::make('cancel')->table($batch))
        ->assertNotified(trans('admin/node.transfer_batch.notifications.cancel_unconfirmed'));

    expect($batch->refresh()->status)->toBe(ServerTransferBatchStatus::Running);
});

it('shows batch progress and failures and can cancel a batch', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    fakeNodeStates([]);
    $target = Node::factory()->create();
    Allocation::factory()->create(['node_id' => $target->id, 'ip' => '10.0.0.5', 'port' => 1]);
    $server = massActionServer($this->node, 'server', 25565);

    $service = app(ServerTransferBatchService::class);
    $batch = $service->create($this->node, $target, [$server->id => ['ip' => '10.0.0.5', 'move_backups' => true]]);
    $batch->items()->update(['status' => ServerTransferBatchItemStatus::Failed, 'error' => 'it broke']);

    livewire(ServerTransferBatchesRelationManager::class, ['ownerRecord' => $this->node, 'pageClass' => EditNode::class])
        ->assertCanSeeTableRecords([$batch])
        ->assertSee('1 / 1')
        ->assertActionVisible(TestAction::make('failures')->table($batch))
        ->callAction(TestAction::make('cancel')->table($batch));

    expect($batch->refresh()->status->value)->toBe('cancelled');
});
