<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\Role;
use App\Models\User;
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
