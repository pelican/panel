<?php

use App\Enums\WebhookScope;
use App\Extensions\Webhooks\Schemas\BaseSchema;
use App\Extensions\Webhooks\WebhookTypeService;
use App\Filament\Server\Resources\Webhooks\Pages\CreateWebhook;
use App\Filament\Server\Resources\Webhooks\Pages\EditWebhook;
use App\Filament\Server\Resources\Webhooks\Pages\ViewWebhook;
use App\Models\Role;
use App\Models\WebhookConfiguration;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;

use function Pest\Livewire\livewire;

/**
 * Mimics a plugin type (like Discord) that collapses its form fields into the
 * payload column on save and expands them back out when filling the form.
 */
class PayloadCollapsingSchema extends BaseSchema
{
    public function getId(): string
    {
        return 'collapsing';
    }

    public function getFormComponents(WebhookScope $scope): array
    {
        return [
            TextInput::make('username'),
            TextInput::make('content'),
        ];
    }

    public function mutateFormDataBeforeSave(array $data): array
    {
        $data['payload'] = array_filter([
            'username' => $data['username'] ?? null,
            'content' => $data['content'] ?? null,
        ]);

        unset($data['username'], $data['content']);

        return $data;
    }

    public function mutateFormDataBeforeFill(array $data): array
    {
        $data['username'] = $data['payload']['username'] ?? null;
        $data['content'] = $data['payload']['content'] ?? null;

        return $data;
    }
}

function createCollapsingWebhook(int $serverId): WebhookConfiguration
{
    return WebhookConfiguration::factory()->create([
        'server_id' => $serverId,
        'scope' => WebhookScope::Server,
        'type' => 'collapsing',
        'payload' => ['username' => 'Valheim-Server', 'content' => 'Valheim-Server: {{event}}'],
        'events' => ['server:power.start'],
    ]);
}

beforeEach(function () {
    Filament::setCurrentPanel('server');

    [$this->user, $this->server] = generateTestAccount();
    $this->user->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->user);
    Filament::setTenant($this->server);

    app(WebhookTypeService::class)->register(new PayloadCollapsingSchema());
});

afterEach(fn () => Filament::setCurrentPanel(null));

it('persists the type payload when creating a server webhook', function () {
    livewire(CreateWebhook::class)
        ->fillForm([
            'type' => 'collapsing',
            'endpoint' => 'https://example.com/hook',
            'name' => 'Notifier',
            'events' => ['server:power.start'],
            'username' => 'Valheim-Server',
            'content' => 'Valheim-Server: {{event}}',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $webhook = WebhookConfiguration::query()->latest('id')->firstOrFail();

    expect($webhook->server_id)->toBe($this->server->id)
        ->and($webhook->scope)->toBe(WebhookScope::Server)
        ->and($webhook->payload)->toBe(['username' => 'Valheim-Server', 'content' => 'Valheim-Server: {{event}}']);
});

it('fills the payload fields and keeps the payload when editing a server webhook', function () {
    $webhook = createCollapsingWebhook($this->server->id);

    livewire(EditWebhook::class, ['record' => $webhook->getKey()])
        ->assertSchemaStateSet([
            'username' => 'Valheim-Server',
            'content' => 'Valheim-Server: {{event}}',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($webhook->refresh()->payload)->toBe(['username' => 'Valheim-Server', 'content' => 'Valheim-Server: {{event}}']);
});

it('fills the payload fields when viewing a server webhook', function () {
    $webhook = createCollapsingWebhook($this->server->id);

    livewire(ViewWebhook::class, ['record' => $webhook->getKey()])
        ->assertSchemaStateSet([
            'username' => 'Valheim-Server',
            'content' => 'Valheim-Server: {{event}}',
        ]);
});
