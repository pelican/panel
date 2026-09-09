<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Mounts\Pages\CreateMount;
use App\Filament\Admin\Resources\Mounts\Pages\EditMount;
use App\Models\Mount;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);
});
afterEach(fn () => Filament::setCurrentPanel(null));

it('can create a mount', function () {
    livewire(CreateMount::class)
        ->fillForm([
            'name' => 'Test Mount',
            'source' => '/mnt/test-source',
            'target' => '/srv/test-target',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('mounts', [
        'name' => 'Test Mount',
        'source' => '/mnt/test-source',
        'target' => '/srv/test-target',
    ]);
});

it('validates required fields when creating a mount', function () {
    livewire(CreateMount::class)
        ->fillForm([
            'name' => 'Test Mount',
            'target' => '/srv/test-target',
        ])
        ->call('create')
        ->assertHasFormErrors(['source' => 'required']);

    $this->assertDatabaseMissing('mounts', ['name' => 'Test Mount']);
});

it('can edit a mount', function () {
    $mount = Mount::query()->create([
        'uuid' => Str::uuid()->toString(),
        'name' => 'Original Mount',
        'source' => '/mnt/original',
        'target' => '/srv/original',
        'read_only' => false,
        'user_mountable' => false,
    ]);

    livewire(EditMount::class, ['record' => $mount->getKey()])
        ->fillForm(['name' => 'Renamed Mount'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('mounts', ['id' => $mount->id, 'name' => 'Renamed Mount']);
});

it('can delete a mount', function () {
    $mount = Mount::query()->create([
        'uuid' => Str::uuid()->toString(),
        'name' => 'Doomed Mount',
        'source' => '/mnt/doomed',
        'target' => '/srv/doomed',
        'read_only' => false,
        'user_mountable' => false,
    ]);

    livewire(EditMount::class, ['record' => $mount->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertDatabaseMissing('mounts', ['id' => $mount->id]);
});

it('non root admin without permission cannot create mounts', function () {
    $role = Role::factory()->create(['name' => 'Egg Viewer', 'guard_name' => 'web']);
    // Egg permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Egg->viewAny(), 'web'));
    [$user] = generateTestAccount();
    $user->syncRoles($role);

    $this->actingAs($user);
    livewire(CreateMount::class)->assertForbidden();
});
