<?php

namespace App\Tests\Filament\Admin;

use App\Contracts\Plugins\HasTheme;
use App\Enums\PluginCategory;
use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Filament\Admin\Resources\Plugins\PluginResource;
use App\Models\Plugin;
use App\Models\Role;
use App\Services\Helpers\PluginService;
use App\Tests\Integration\IntegrationTestCase;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Tables\Table;

class PluginEnableActionTest extends IntegrationTestCase
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

    private function enableAction(PluginCategory $category, string $class = 'Missing', bool $anyThemeEnabled = false): Action
    {
        $this->mock(PluginService::class)->allows('hasAnyThemeEnabled')->andReturn($anyThemeEnabled);

        $plugin = (new Plugin())->forceFill([
            'id' => 'fake',
            'name' => 'Fake',
            'category' => $category->value,
            'namespace' => __NAMESPACE__,
            'class' => $class,
        ]);

        $table = PluginResource::table(Table::make(new ListPlugins()));

        return $table->getAction('exclude_enable')->record($plugin);
    }

    public function test_a_regular_plugin_enables_without_a_modal(): void
    {
        $this->assertFalse($this->enableAction(PluginCategory::Plugin, anyThemeEnabled: true)->shouldOpenModal());
    }

    public function test_a_legacy_theme_enables_without_a_modal_when_no_theme_is_enabled(): void
    {
        $this->assertFalse($this->enableAction(PluginCategory::Theme)->shouldOpenModal());
    }

    public function test_a_legacy_theme_asks_for_confirmation_when_another_theme_is_enabled(): void
    {
        $action = $this->enableAction(PluginCategory::Theme, anyThemeEnabled: true);

        $this->assertTrue($action->shouldOpenModal());
        $this->assertTrue($action->isConfirmationRequired());
    }

    public function test_a_switchable_theme_offers_to_become_the_default(): void
    {
        $action = $this->enableAction(PluginCategory::Theme, class: 'SwitchableTheme', anyThemeEnabled: true);

        $this->assertTrue($action->shouldOpenModal());
        $this->assertFalse($action->isConfirmationRequired());
    }
}

final class SwitchableTheme implements HasTheme
{
    public function getId(): string
    {
        return 'fake';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    public function getThemeColors(): array
    {
        return [];
    }

    public function getThemeFont(): ?string
    {
        return null;
    }

    public function getThemeViteEntry(): ?string
    {
        return null;
    }

    public function getThemeRenderHooks(): array
    {
        return [];
    }
}
