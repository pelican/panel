<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\ApiKeys\Pages\CreateApiKey;
use App\Filament\Admin\Resources\ApiKeys\Pages\ListApiKeys;
use App\Models\ApiKey;
use App\Models\Role;
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

it('can create an application api key', function () {
    livewire(CreateApiKey::class)
        ->fillForm(['memo' => 'test key'])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('api_keys', [
        'key_type' => ApiKey::TYPE_APPLICATION,
        'user_id' => $this->admin->id,
        'memo' => 'test key',
    ]);
});

it('stores the selected resource permissions on the key', function () {
    livewire(CreateApiKey::class)
        ->fillForm([
            'memo' => 'permissioned key',
            'permissions_server' => 3,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $apiKey = ApiKey::query()->where('memo', 'permissioned key')->firstOrFail();
    expect($apiKey->permissions['server'])->toBe(3);
});

it('can delete an api key', function () {
    $apiKey = ApiKey::factory()->create([
        'key_type' => ApiKey::TYPE_APPLICATION,
        'user_id' => $this->admin->id,
    ]);

    livewire(ListApiKeys::class)
        ->callAction(TestAction::make('delete')->table($apiKey));

    $this->assertDatabaseMissing('api_keys', ['id' => $apiKey->id]);
});

it('non root admin without permission cannot create api keys', function () {
    $role = Role::factory()->create(['name' => 'Egg Viewer', 'guard_name' => 'web']);
    // Egg permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Egg->viewAny(), 'web'));
    [$user] = generateTestAccount();
    $user->syncRoles($role);

    $this->actingAs($user);
    livewire(CreateApiKey::class)->assertForbidden();
});
