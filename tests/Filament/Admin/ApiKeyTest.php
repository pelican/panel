<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\ApiKeys\Pages\CreateApiKey;
use App\Filament\Admin\Resources\ApiKeys\Pages\ListApiKeys;
use App\Models\ApiKey;
use App\Models\Role;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
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

/**
 * A create-key link from Pelican Hub: the OAuth 2.0 authorization request plus the prefill.
 *
 * @return array<string, mixed>
 */
function hubAuthorizationQuery(string $verifier, array $overrides = []): array
{
    return [
        'memo' => 'Pelican Hub',
        'permissions' => ['plugin' => '3'],
        'response_type' => 'code',
        'client_id' => 'pelican-hub',
        'redirect_uri' => 'https://hub.example.com/panels/connect/callback',
        'state' => 'state-from-the-hub-session',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
        ...$overrides,
    ];
}

/** Presses Create on a create-key page opened from the query and returns where the browser goes. */
function createKeyFrom(array $query): string
{
    return Livewire::withQueryParams($query)
        ->test(CreateApiKey::class)
        ->call('create')
        ->assertHasNoFormErrors()
        ->effects['redirect'];
}

/** @return array<string, string> */
function tokenRequest(string $code, string $verifier, array $overrides = []): array
{
    return [
        'grant_type' => 'authorization_code',
        'client_id' => 'pelican-hub',
        'redirect_uri' => 'https://hub.example.com/panels/connect/callback',
        'code' => $code,
        'code_verifier' => $verifier,
        ...$overrides,
    ];
}

beforeEach(fn () => config()->set('panel.plugin.hub_url', 'https://hub.example.com'));

it('sends the admin back to the hub with a one-time code the hub swaps for the key', function () {
    $verifier = str_repeat('v', 64);

    $redirect = createKeyFrom(hubAuthorizationQuery($verifier));

    expect($redirect)->toStartWith('https://hub.example.com/panels/connect/callback?');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $params);
    expect($params['state'])->toBe('state-from-the-hub-session');

    $apiKey = ApiKey::query()->where('memo', 'Pelican Hub')->firstOrFail();

    $this->postJson('/oauth/token', tokenRequest($params['code'], $verifier))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['access_token' => $apiKey->identifier . $apiKey->token, 'token_type' => 'Bearer']);

    // One use only.
    $this->postJson('/oauth/token', tokenRequest($params['code'], $verifier))
        ->assertStatus(400)
        ->assertExactJson(['error' => 'invalid_grant']);
});

it('only gives the key to whoever holds the PKCE verifier, and burns the code on a wrong one', function () {
    $verifier = str_repeat('v', 64);
    parse_str((string) parse_url(createKeyFrom(hubAuthorizationQuery($verifier)), PHP_URL_QUERY), $params);

    $this->postJson('/oauth/token', tokenRequest($params['code'], str_repeat('w', 64)))
        ->assertStatus(400)
        ->assertExactJson(['error' => 'invalid_grant']);

    $this->postJson('/oauth/token', tokenRequest($params['code'], $verifier))
        ->assertStatus(400)
        ->assertExactJson(['error' => 'invalid_grant']);
});

it('ignores an authorization request that is not for the hub callback', function (array $overrides) {
    $redirect = createKeyFrom(hubAuthorizationQuery(str_repeat('v', 64), $overrides));

    expect($redirect)->toBe(ListApiKeys::getUrl());
})->with([
    'other host' => [['redirect_uri' => 'https://evil.example.com/panels/connect/callback']],
    'lookalike host' => [['redirect_uri' => 'https://hub.example.com.evil.com/panels/connect/callback']],
    'plain http' => [['redirect_uri' => 'http://hub.example.com/panels/connect/callback']],
    'other port' => [['redirect_uri' => 'https://hub.example.com:8443/panels/connect/callback']],
    'other path on the hub' => [['redirect_uri' => 'https://hub.example.com/somewhere-else']],
    'other client' => [['client_id' => 'someone-else']],
    'no PKCE' => [['code_challenge' => null]],
    'plain PKCE' => [['code_challenge_method' => 'plain']],
    'no state' => [['state' => null]],
]);

it('says where the admin goes after Create', function () {
    Livewire::withQueryParams(hubAuthorizationQuery(str_repeat('v', 64)))
        ->test(CreateApiKey::class)
        ->assertSee(trans('admin/apikey.return_to', ['host' => 'hub.example.com']));
});

it('rejects token requests that are not an authorization code for the hub', function (array $overrides, int $status, string $error) {
    $this->postJson('/oauth/token', tokenRequest('unknown-code', str_repeat('v', 64), $overrides))
        ->assertStatus($status)
        ->assertExactJson(['error' => $error]);
})->with([
    'other grant' => [['grant_type' => 'client_credentials'], 400, 'unsupported_grant_type'],
    'other client' => [['client_id' => 'someone-else'], 401, 'invalid_client'],
    'other redirect uri' => [['redirect_uri' => 'https://evil.example.com/cb'], 400, 'invalid_grant'],
    'unknown code' => [[], 400, 'invalid_grant'],
]);

it('keeps up to 255 characters of a prefilled description, counting characters not width', function () {
    Livewire::withQueryParams(['memo' => str_repeat('界', 300)])
        ->test(CreateApiKey::class)
        ->assertSchemaStateSet(['memo' => str_repeat('界', 255)]);
});
