<?php

use App\Enums\RolePermissionModels;
use App\Enums\ServerState;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\RelationManagers\ServersRelationManager;
use App\Models\Role;
use App\Models\Subuser;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
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

it('can create a user', function () {
    livewire(CreateUser::class)
        ->fillForm([
            'username' => 'newuser',
            'email' => 'new@example.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'username' => 'newuser',
        'email' => 'new@example.com',
    ]);
});

it('can create a user with a role', function () {
    $role = Role::factory()->create(['name' => 'Support Staff', 'guard_name' => 'web']);

    livewire(CreateUser::class)
        ->fillForm([
            'username' => 'staffuser',
            'email' => 'staff@example.com',
            'roles' => [$role->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'staffuser')->firstOrFail();
    expect($user->hasRole('Support Staff'))->toBeTrue();
});

it('validates required fields when creating a user', function () {
    livewire(CreateUser::class)
        ->fillForm(['username' => 'nomail'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'required']);

    $this->assertDatabaseMissing('users', ['username' => 'nomail']);
});

it('can edit a user', function () {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['username' => 'renameduser'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => 'renameduser']);
});

it('can delete a user', function () {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('non root admin without permission cannot create users', function () {
    $role = Role::factory()->create(['name' => 'Egg Viewer', 'guard_name' => 'web']);
    // Egg permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Egg->viewAny(), 'web'));
    [$user] = generateTestAccount();
    $user->syncRoles($role);

    $this->actingAs($user);
    livewire(CreateUser::class)->assertForbidden();
});

it('only offers suspend all when the user has unsuspended servers', function () {
    [$user, $server] = generateTestAccount();
    $server->forceFill(['status' => ServerState::Suspended])->saveQuietly();
    createServerModel(); // Another user's unsuspended server.

    livewire(ServersRelationManager::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
        ->assertActionHidden(TestAction::make('toggle_suspend')->table())
        ->assertActionVisible(TestAction::make('toggle_unsuspend')->table());
});

it('lists the servers a user owns or is a subuser of', function () {
    $user = User::factory()->create();
    $owned = createServerModel(['owner_id' => $user->id]);
    $shared = createServerModel();
    $notShared = createServerModel();

    Subuser::query()->create(['user_id' => $user->id, 'server_id' => $shared->id, 'permissions' => ['control.console', 'file.read']]);
    // Another user's access must not show up.
    Subuser::query()->create(['user_id' => User::factory()->create()->id, 'server_id' => $notShared->id, 'permissions' => ['control.console']]);

    livewire(ServersRelationManager::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
        ->assertCanSeeTableRecords([$owned, $shared])
        ->assertCanNotSeeTableRecords([$notShared])
        ->assertTableColumnStateSet('access', trans('admin/server.owner'), $owned)
        ->assertTableColumnStateSet('access', trans('admin/user.subuser'), $shared);
});
