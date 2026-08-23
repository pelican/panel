<?php

namespace App\Tests\Integration\Installer;

use App\Livewire\Installer\PanelInstaller;
use App\Livewire\Installer\Steps\DatabaseStep;
use App\Tests\Integration\IntegrationTestCase;
use Livewire\Livewire;
use ReflectionMethod;

class PanelInstallerTest extends IntegrationTestCase
{
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
}
