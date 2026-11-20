<?php

use App\Enums\SubuserPermission;
use App\Filament\Server\Resources\Databases\Pages\ListDatabases;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Server;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(fn () => Filament::setCurrentPanel('server'));
afterEach(fn () => Filament::setCurrentPanel(null));

function createTestDatabase(Server $server): Database
{
    $host = DatabaseHost::factory()->create(['host' => '127.0.0.1', 'port' => 1]);

    return Database::factory()->create(['server_id' => $server->id, 'database_host_id' => $host->id]);
}

it('does not send the database password to a subuser without view password permission', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::DatabaseRead]);
    $database = createTestDatabase($server);

    $this->actingAs($user);
    Filament::setTenant($server);

    $component = livewire(ListDatabases::class)
        ->mountAction(TestAction::make('view')->table($database))
        ->assertSchemaStateSet([
            'username' => $database->username,
            'password' => null,
            'jdbc' => null,
        ]);

    expect(json_encode($component->instance()->mountedActions))
        ->not->toContain($database->password)
        ->not->toContain(urlencode($database->password));
});

it('sends the database password to a subuser with view password permission', function () {
    [$user, $server] = generateTestAccount([SubuserPermission::DatabaseRead, SubuserPermission::DatabaseViewPassword]);
    $database = createTestDatabase($server);

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(ListDatabases::class)
        ->mountAction(TestAction::make('view')->table($database))
        ->assertSchemaStateSet([
            'password' => $database->password,
            'jdbc' => $database->jdbc,
        ]);
});
