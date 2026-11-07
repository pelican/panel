<?php

use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Nodes\RelationManagers\AllocationsRelationManager;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);

    Http::fake(['*/api/system/ips' => Http::response(['ip_addresses' => ['10.0.0.1', '10.0.0.2']])]);
});
afterEach(fn () => Filament::setCurrentPanel(null));

it('only treats ips taken on the same node as unavailable', function () {
    $node = Node::factory()->create();
    $allocation = Allocation::factory()->create(['node_id' => $node->id, 'ip' => '10.0.0.1', 'port' => 25565]);
    Allocation::factory()->create(['node_id' => Node::factory()->create()->id, 'ip' => '10.0.0.2', 'port' => 25565]);

    $column = livewire(AllocationsRelationManager::class, ['ownerRecord' => $node, 'pageClass' => EditNode::class])
        ->instance()->getTable()->getColumn('ip');

    expect($column->record($allocation)->getOptions())->toHaveKeys(['10.0.0.1', '10.0.0.2']);
});

it('does not query per row', function () {
    $node = Node::factory()->create();
    $server = createServerModel(['node_id' => $node->id]);

    $count = function () use ($node) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        livewire(AllocationsRelationManager::class, ['ownerRecord' => $node, 'pageClass' => EditNode::class])->assertSuccessful();

        return count(DB::getQueryLog());
    };

    $count(); // Warm the permission, settings and node ip caches.
    $baseline = $count();

    foreach (range(1, 9) as $port) {
        Allocation::factory()->create(['node_id' => $node->id, 'port' => 3000 + $port, 'server_id' => $server->id]);
    }

    expect($count())->toBe($baseline);
});
