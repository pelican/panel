<?php

use App\Filament\Admin\Pages\Settings;
use App\Filament\Admin\Resources\ApiKeys\Pages\CreateApiKey;
use App\Filament\Admin\Resources\Mounts\Pages\CreateMount;
use App\Filament\Admin\Resources\Nodes\Pages\CreateNode;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Servers\Pages\CreateServer;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Admin\Resources\Webhooks\Pages\CreateWebhookConfiguration;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Forms\Components\ToggleButtons;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);
});
afterEach(fn () => Filament::setCurrentPanel(null));

// A single-select ToggleButtons that is not required renders as a toggle the user can click
// again to clear, which sends null to closures that expect one of the options (e.g. swap_support).
it('makes every editable single-select toggle required', function (string $page, ?Closure $record) {
    $params = $record ? ['record' => $record()->getKey()] : [];

    // Settings disables every field when the environment file is not writable, as in CI.
    $path = sys_get_temp_dir().'/pelican-toggles-test-'.getmypid();
    @mkdir($path);
    file_put_contents("$path/.env", '');
    app()->useEnvironmentPath($path);

    $toggles = collect(livewire($page, $params)->instance()->getSchema('form')->getFlatComponents(withHidden: true))
        ->filter(fn ($component) => $component instanceof ToggleButtons && !$component->isMultiple() && !$component->isDisabled())
        // Option-less toggles only carry a hint, and an unset MAIL_SCHEME lets Laravel infer it from the port.
        ->filter(fn (ToggleButtons $toggle) => filled($toggle->getOptions()) && $toggle->getName() !== 'MAIL_SCHEME');

    @unlink("$path/.env");
    @rmdir($path);

    expect($toggles)->not->toBeEmpty()
        ->and($toggles->reject->isRequired()->map->getName()->values()->all())->toBe([]);
})->with([
    'create server' => [CreateServer::class, null],
    'edit server' => [EditServer::class, fn () => Server::factory()->withNode()->create()],
    'create node' => [CreateNode::class, null],
    'edit node' => [EditNode::class, fn () => Node::factory()->create()],
    'create mount' => [CreateMount::class, null],
    'create api key' => [CreateApiKey::class, null],
    'create webhook' => [CreateWebhookConfiguration::class, null],
    'settings' => [Settings::class, null],
]);
