<?php

namespace App\Tests\Integration\Installer;

use App\Jobs\InstallEgg;
use App\Jobs\Plugin\InstallHubPlugin;
use App\Jobs\Plugin\InstallPlugin;
use App\Livewire\Installer\PanelInstaller;
use App\Livewire\Installer\Steps\DatabaseStep;
use App\Models\Plugin;
use App\Models\User;
use App\Tests\Integration\IntegrationTestCase;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use ReflectionMethod;
use ZipArchive;

class PanelInstallerTest extends IntegrationTestCase
{
    private const HUB = 'https://hub.example.test';

    public function test_sqlite_defaults_remain_relative_and_do_not_include_connection_fields(): void
    {
        config()->set('app.installed', false);
        config()->set('database.default', 'sqlite');

        $component = Livewire::test(PanelInstaller::class);

        $component
            ->assertSet('data.env_database.DB_CONNECTION', 'sqlite')
            ->assertSet('data.env_database.DB_DATABASE', 'database.sqlite')
            ->assertSet('data.env_database.DB_HOST', null)
            ->assertSet('data.env_database.DB_PORT', null)
            ->assertSet('data.env_database.DB_USERNAME', null)
            ->assertSet('data.env_database.DB_PASSWORD', null);

        $component->assertSuccessful();
    }

    public function test_database_fields_are_hydrated_from_a_preconfigured_non_sqlite_connection(): void
    {
        config()->set('app.installed', false);
        config()->set('database.default', 'mariadb');
        config()->set('database.connections.mariadb', [
            'driver' => 'mariadb',
            'host' => 'pelican-db',
            'port' => '3307',
            'database' => 'pelican',
            'username' => 'pelican-user',
            'password' => 'pelican-password',
        ]);

        $component = Livewire::test(PanelInstaller::class);

        $component
            ->assertSet('data.env_database.DB_CONNECTION', 'mariadb')
            ->assertSet('data.env_database.DB_HOST', 'pelican-db')
            ->assertSet('data.env_database.DB_PORT', '3307')
            ->assertSet('data.env_database.DB_DATABASE', 'pelican')
            ->assertSet('data.env_database.DB_USERNAME', 'pelican-user')
            ->assertSet('data.env_database.DB_PASSWORD', null);

        $component
            ->set('data.env_database.DB_CONNECTION', 'sqlite')
            ->assertSet('data.env_database.DB_DATABASE', 'database.sqlite')
            ->assertSet('data.env_database.DB_HOST', null)
            ->assertSet('data.env_database.DB_PORT', null)
            ->assertSet('data.env_database.DB_USERNAME', null)
            ->assertSet('data.env_database.DB_PASSWORD', null)
            ->set('data.env_database.DB_CONNECTION', 'mariadb')
            ->assertSet('data.env_database.DB_HOST', 'pelican-db')
            ->assertSet('data.env_database.DB_PORT', '3307')
            ->assertSet('data.env_database.DB_DATABASE', 'pelican')
            ->assertSet('data.env_database.DB_USERNAME', 'pelican-user')
            ->assertSet('data.env_database.DB_PASSWORD', null);

        $component->assertSuccessful();
    }

    public function test_postgresql_uses_its_default_port_when_no_port_is_configured(): void
    {
        config()->set('app.installed', false);
        config()->set('database.default', 'pgsql');
        config()->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => 'pelican-db',
            'database' => 'pelican',
            'username' => 'pelican-user',
            'password' => 'pelican-password',
        ]);

        Livewire::test(PanelInstaller::class)
            ->assertSet('data.env_database.DB_CONNECTION', 'pgsql')
            ->assertSet('data.env_database.DB_PORT', '5432')
            ->assertSuccessful();
    }

    public function test_empty_database_password_uses_the_configured_password(): void
    {
        config()->set('database.default', 'mariadb');
        config()->set('database.connections.mariadb.password', 'pelican-password');

        $resolver = new ReflectionMethod(DatabaseStep::class, 'getConnectionPassword');

        $this->assertSame('pelican-password', $resolver->invoke(null, 'mariadb', ''));
        $this->assertSame('pelican-password', $resolver->invoke(null, 'mariadb', null));
        $this->assertSame('entered-password', $resolver->invoke(null, 'mariadb', 'entered-password'));
    }

    public function test_plugin_step_lists_free_hub_plugins_in_tabs_by_type(): void
    {
        config()->set('app.installed', false);
        config()->set('panel.plugin.hub_url', self::HUB);

        Http::fake([self::HUB . '/api/plugins*' => Http::response(['data' => [
            $this->hubPlugin('a-free-plugin', 'A Free Plugin'),
            $this->hubPlugin('a-free-theme', 'A Free Theme', type: 'theme'),
            $this->hubPlugin('a-paid-plugin', 'A Paid Plugin', pricing: 'paid'),
        ]])]);

        Livewire::test(PanelInstaller::class)
            ->call('loadCatalogs')
            ->assertSuccessful()
            ->assertDontSee(trans('installer.plugin.loading'))
            ->assertSet('data.plugins', ['plugin' => [], 'theme' => []])
            ->assertSee('A Free Plugin')
            ->assertSee('A Free Theme')
            ->assertSee('by Plugin Author - Does free things')
            ->assertDontSee('A Paid Plugin')
            ->assertSeeHtml('value="a-free-plugin"');
    }

    public function test_plugin_step_can_be_passed_when_the_hub_is_unreachable(): void
    {
        config()->set('app.installed', false);
        config()->set('panel.plugin.hub_url', self::HUB);

        Http::fake([self::HUB . '/*' => Http::response('down', 503)]);

        Livewire::test(PanelInstaller::class)
            ->call('loadCatalogs')
            ->assertSuccessful()
            ->assertSee(trans('installer.plugin.unavailable'));
    }

    public function test_the_page_renders_loading_indicators_without_any_remote_requests(): void
    {
        config()->set('app.installed', false);

        Http::fake();

        Livewire::test(PanelInstaller::class)
            ->assertSuccessful()
            ->assertSee(trans('installer.egg.loading'))
            ->assertSee(trans('installer.plugin.loading'))
            ->assertSet('eggIndex', null)
            ->assertSet('hubPlugins', null);

        Http::assertNothingSent();
    }

    public function test_the_lists_load_once_and_later_requests_make_no_remote_requests(): void
    {
        config()->set('app.installed', false);
        config()->set('panel.cdn.egg_index_url', 'https://eggs.example.test/index.json');
        config()->set('panel.plugin.hub_url', self::HUB);

        Http::fake([
            'eggs.example.test/*' => Http::response(['nests' => [[
                'nest_type' => 'Minecraft',
                'Eggs' => [['egg' => ['name' => 'Paper'], 'download_url' => 'https://eggs.example.test/paper.json']],
            ]]]),
            self::HUB . '/api/plugins*' => Http::response(['data' => [$this->hubPlugin('a-free-plugin', 'A Free Plugin')]]),
        ]);

        $component = Livewire::test(PanelInstaller::class)
            ->call('loadCatalogs')
            ->assertDontSee(trans('installer.egg.loading'))
            ->assertSee('Paper')
            ->assertSee('A Free Plugin')
            ->assertSet('data.eggs', ['minecraft' => []])
            ->set('data.plugins.plugin', ['a-free-plugin']);

        Http::assertSentCount(2);

        // Neither a later render nor loading again goes back to the network or loses the selection.
        $component
            ->call('loadCatalogs')
            ->call('$refresh')
            ->assertSee('A Free Plugin')
            ->assertSet('data.plugins.plugin', ['a-free-plugin']);

        Http::assertSentCount(2);
    }

    public function test_selected_plugins_are_queued_for_the_admin_once_each(): void
    {
        config()->set('app.installed', false);
        config()->set('panel.plugin.hub_url', self::HUB);

        Http::fake([self::HUB . '/*' => Http::response('down', 503)]);
        Queue::fake();

        $user = User::factory()->create();

        Livewire::test(PanelInstaller::class)
            ->set('data.plugins', ['plugin' => ['first', 'second'], 'theme' => ['second']])
            ->instance()
            ->installPlugins($user);

        Queue::assertPushed(InstallHubPlugin::class, 2);
        Queue::assertPushed(InstallHubPlugin::class, fn (InstallHubPlugin $job) => $job->slug === 'first' && $job->user->is($user));
        Queue::assertPushed(InstallHubPlugin::class, fn (InstallHubPlugin $job) => $job->slug === 'second');
    }

    public function test_nothing_is_queued_when_no_plugins_are_selected(): void
    {
        config()->set('app.installed', false);
        config()->set('panel.plugin.hub_url', self::HUB);

        Http::fake([self::HUB . '/*' => Http::response('down', 503)]);
        Queue::fake();

        Livewire::test(PanelInstaller::class)
            ->instance()
            ->installPlugins(User::factory()->create());

        Queue::assertNothingPushed();
    }

    public function test_hub_plugin_job_downloads_a_verified_release_then_queues_its_install(): void
    {
        config()->set('panel.plugin.hub_url', self::HUB);

        $slug = 'installer-hub-plugin';
        $path = tempnam(sys_get_temp_dir(), 'hub');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString("$slug/plugin.json", (string) json_encode(['id' => $slug, 'name' => 'Installer Hub Plugin', 'version' => '1.0.0']));
        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        Http::fake([
            self::HUB . "/api/plugins/$slug" => Http::response($this->hubPlugin($slug, 'Installer Hub Plugin')),
            self::HUB . "/plugins/$slug/update.json" => Http::response(['*' => [
                'version' => '1.0.0',
                'download_url' => self::HUB . "/plugins/$slug/download/1/$slug.zip",
                'sha256' => hash('sha256', $bytes),
            ]]),
            self::HUB . "/plugins/$slug/download/*" => Http::response($bytes),
        ]);
        Queue::fake([InstallPlugin::class]);

        try {
            app()->call([new InstallHubPlugin(User::factory()->create(), $slug), 'handle']);

            $this->assertFileExists(plugin_path($slug, 'plugin.json'));
            Queue::assertPushed(InstallPlugin::class, fn (InstallPlugin $job) => $job->pluginId === $slug && !$job->reinstall);
        } finally {
            File::deleteDirectory(plugin_path($slug));
            Plugin::refreshRows();
        }
    }

    public function test_hub_plugin_job_refuses_a_paid_plugin_and_tells_the_admin(): void
    {
        config()->set('panel.plugin.hub_url', self::HUB);

        $slug = 'installer-paid-plugin';

        Http::fake([self::HUB . "/api/plugins/$slug" => Http::response($this->hubPlugin($slug, 'Paid', pricing: 'paid'))]);
        Queue::fake([InstallPlugin::class]);

        $user = User::factory()->create();

        app()->call([new InstallHubPlugin($user, $slug), 'handle']);

        $this->assertDirectoryDoesNotExist(plugin_path($slug));
        Queue::assertNotPushed(InstallPlugin::class);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'update.json'));
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_no_egg_notification_is_sent_when_no_eggs_are_selected(): void
    {
        config()->set('app.installed', false);

        Http::fake();
        Queue::fake();

        Livewire::test(PanelInstaller::class)
            ->set('data.eggs', ['minecraft' => [], 'rust' => []])
            ->instance()
            ->installEggs();

        Queue::assertNothingPushed();
        Notification::assertNotNotified(trans('installer.egg.background_install_started'));
    }

    public function test_selected_eggs_are_queued_and_counted_in_the_notification(): void
    {
        config()->set('app.installed', false);

        Http::fake();
        Queue::fake();

        Livewire::test(PanelInstaller::class)
            ->set('data.eggs', ['minecraft' => ['https://eggs.example.test/paper.json', 'https://eggs.example.test/forge.json'], 'rust' => []])
            ->instance()
            ->installEggs();

        Queue::assertPushed(InstallEgg::class, 2);
        Notification::assertNotified(
            Notification::make()
                ->title(trans('installer.egg.background_install_started'))
                ->body(trans('installer.egg.background_install_description', ['count' => 2]))
                ->success()
                ->persistent()
        );
    }

    /** @return array<string, mixed> */
    private function hubPlugin(string $slug, string $title, string $type = 'plugin', string $pricing = 'free'): array
    {
        return [
            'slug' => $slug,
            'title' => $title,
            'type' => $type,
            'pricing_type' => $pricing,
            'author_name' => 'Plugin Author',
            'description' => 'Does **free** things',
        ];
    }
}
