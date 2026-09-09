<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Webhooks\Pages\CreateWebhookConfiguration;
use App\Filament\Admin\Resources\Webhooks\Pages\EditWebhookConfiguration;
use App\Models\Role;
use App\Models\Server;
use App\Models\WebhookConfiguration;
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

it('can create a webhook configuration', function () {
    livewire(CreateWebhookConfiguration::class)
        ->fillForm([
            'name' => 'Notifier',
            'description' => 'Notifies on new servers',
            'endpoint' => 'https://example.com/hook',
            'events' => ['eloquent.created: '.Server::class],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('webhook_configurations', [
        'name' => 'Notifier',
        'endpoint' => 'https://example.com/hook',
    ]);
});

it('validates required fields when creating a webhook configuration', function () {
    livewire(CreateWebhookConfiguration::class)
        ->fillForm([
            'name' => 'Broken Hook',
            'description' => 'Missing its endpoint',
            'events' => ['eloquent.created: '.Server::class],
        ])
        ->call('create')
        ->assertHasFormErrors(['endpoint' => 'required']);
});

it('can edit a webhook configuration', function () {
    $webhookConfig = WebhookConfiguration::factory()->create([
        'events' => ['eloquent.created: '.Server::class],
    ]);

    livewire(EditWebhookConfiguration::class, ['record' => $webhookConfig->getKey()])
        ->fillForm(['name' => 'Renamed Hook'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('webhook_configurations', ['id' => $webhookConfig->id, 'name' => 'Renamed Hook']);
});

it('can delete a webhook configuration', function () {
    $webhookConfig = WebhookConfiguration::factory()->create();

    livewire(EditWebhookConfiguration::class, ['record' => $webhookConfig->getKey()])
        ->callAction(DeleteAction::class);

    $this->assertSoftDeleted('webhook_configurations', ['id' => $webhookConfig->id]);
});

it('non root admin without permission cannot create webhook configurations', function () {
    $role = Role::factory()->create(['name' => 'Egg Viewer', 'guard_name' => 'web']);
    // Egg permission is on purpose, we check the wrong permissions.
    $role->givePermissionTo(Permission::findOrCreate(RolePermissionModels::Egg->viewAny(), 'web'));
    [$user] = generateTestAccount();
    $user->syncRoles($role);

    $this->actingAs($user);
    livewire(CreateWebhookConfiguration::class)->assertForbidden();
});
