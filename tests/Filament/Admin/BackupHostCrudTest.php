<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\BackupHosts\Pages\CreateBackupHost;
use App\Filament\Admin\Resources\BackupHosts\Pages\EditBackupHost;
use App\Models\BackupHost;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);
});
afterEach(fn () => Filament::setCurrentPanel(null));

it('can create an s3 backup host', function () {
    livewire(CreateBackupHost::class)
        ->fillForm([
            'name' => 'S3 Host',
            'schema' => 's3',
            'configuration.region' => 'us-east-1',
            'configuration.key' => 'test-key',
            'configuration.secret' => 'test-secret',
            'configuration.bucket' => 'test-bucket',
            'configuration.endpoint' => 'https://s3.example.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('backup_hosts', ['name' => 'S3 Host', 'schema' => 's3']);
});

it('validates required fields when creating a backup host', function () {
    livewire(CreateBackupHost::class)
        ->fillForm(['schema' => 's3'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('can edit a backup host', function () {
    $backupHost = BackupHost::factory()->create();

    livewire(EditBackupHost::class, ['record' => $backupHost->getKey()])
        ->fillForm(['name' => 'Renamed Host'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('backup_hosts', ['id' => $backupHost->id, 'name' => 'Renamed Host']);
});

it('can delete a backup host', function () {
    // The delete action hides when only one backup host exists.
    [$backupHost] = BackupHost::factory(2)->create();

    livewire(EditBackupHost::class, ['record' => $backupHost->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertDatabaseMissing('backup_hosts', ['id' => $backupHost->id]);
});

it('non root admin without permission cannot create backup hosts', function () {
    $role = Role::factory()->create(['name' => 'Egg Viewer', 'guard_name' => 'web']);
    // Egg permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Egg->viewAny(), 'web'));
    [$user] = generateTestAccount();
    $user->syncRoles($role);

    $this->actingAs($user);
    livewire(CreateBackupHost::class)->assertForbidden();
});
