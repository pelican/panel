<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\ApiKeys\Pages\CreateApiKey;
use App\Filament\Admin\Resources\ApiKeys\Pages\ListApiKeys;
use App\Models\ApiKey;
use App\Models\Role;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
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

it('prefills the create form from the query string', function () {
    Livewire::withQueryParams([
        'memo' => 'Pelican Hub',
        'permissions' => ['plugin' => '3', 'server' => '1', 'node' => '7', 'unknown' => '3'],
    ])->test(CreateApiKey::class)
        ->assertSchemaStateSet([
            'memo' => 'Pelican Hub',
            'permissions_plugin' => 3,
            'permissions_server' => 1,
            'permissions_node' => 0,
            'permissions_user' => 0,
        ]);
});

it('posts the new key back to the hub and still redirects to the key list', function () {
    config()->set('panel.plugin.hub_url', 'https://hub.example.com');
    Http::fake(['hub.example.com/*' => Http::response(status: 204)]);

    Livewire::withQueryParams([
        'memo' => 'Pelican Hub',
        'return_url' => 'https://hub.example.com/panels/connect/callback',
        'state' => 'abc123',
    ])->test(CreateApiKey::class)
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(ListApiKeys::getUrl());

    $apiKey = ApiKey::query()->where('memo', 'Pelican Hub')->firstOrFail();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://hub.example.com/panels/connect/callback'
        && $request['key'] === $apiKey->identifier . $apiKey->token
        && $request['state'] === 'abc123');
});

it('posts the key to the hub only once when create is pressed twice', function () {
    config()->set('panel.plugin.hub_url', 'https://hub.example.com');
    Http::fake(['hub.example.com/*' => Http::response(status: 204)]);

    Livewire::withQueryParams(['memo' => 'Pelican Hub', 'return_url' => 'https://hub.example.com/callback'])
        ->test(CreateApiKey::class)
        ->call('create')
        ->call('create');

    expect(ApiKey::query()->where('memo', 'Pelican Hub')->count())->toBe(1);
    Http::assertSentCount(1);
});

it('never posts the key to a url that is not the hub', function (string $returnUrl) {
    config()->set('panel.plugin.hub_url', 'https://hub.example.com');
    Http::fake();

    Livewire::withQueryParams(['memo' => 'key', 'return_url' => $returnUrl])
        ->test(CreateApiKey::class)
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(ListApiKeys::getUrl());

    Http::assertNothingSent();
})->with([
    'other host' => 'https://evil.example.com/steal',
    'lookalike host' => 'https://hub.example.com.evil.com/steal',
    'plain http' => 'http://hub.example.com/callback',
    'other port' => 'https://hub.example.com:8443/callback',
]);

it('warns when the hub does not accept the key', function () {
    config()->set('panel.plugin.hub_url', 'https://hub.example.com');
    Http::fake(['hub.example.com/*' => Http::response(status: 500)]);

    Livewire::withQueryParams(['memo' => 'key', 'return_url' => 'https://hub.example.com/callback'])
        ->test(CreateApiKey::class)
        ->call('create')
        ->assertNotified(trans('admin/apikey.return_failed', ['host' => 'hub.example.com']));

    expect(ApiKey::query()->where('memo', 'key')->exists())->toBeTrue();
});
