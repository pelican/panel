<?php

namespace App\Tests\Integration\Filament;

use App\Filament\Admin\Resources\BackupHosts\Pages\CreateBackupHost;
use App\Filament\Admin\Resources\DatabaseHosts\Pages\CreateDatabaseHost;
use App\Filament\Admin\Resources\Mounts\Pages\CreateMount;
use App\Filament\Admin\Resources\Roles\Pages\CreateRole;
use App\Models\Node;
use App\Models\Role;
use App\Tests\Integration\IntegrationTestCase;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Filament loads the options of a many-to-many select with a DISTINCT query. PostgreSQL cannot
 * compare json columns, so that query must not select the json columns of the nodes table.
 *
 * This lives in the integration suite because it is the suite CI runs against PostgreSQL.
 */
class NodeRelationshipSelectTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        [$admin] = $this->generateTestAccount();
        $admin->syncRoles(Role::getRootAdmin());
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    /** @param  class-string  $page */
    #[DataProvider('pagesWithNodeSelect')]
    public function test_page_with_a_node_select_renders(string $page): void
    {
        // The account's server lives on a node; add a second one that has daemon config stored.
        Node::factory()->create(['daemon_config' => ['debug' => true]]);

        Livewire::test($page)->assertSuccessful();
    }

    /** @return array<string, array{class-string}> */
    public static function pagesWithNodeSelect(): array
    {
        return [
            'mount' => [CreateMount::class],
            'database host' => [CreateDatabaseHost::class],
            'backup host' => [CreateBackupHost::class],
            'role' => [CreateRole::class],
        ];
    }
}
