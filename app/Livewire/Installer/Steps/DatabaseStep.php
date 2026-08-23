<?php

namespace App\Livewire\Installer\Steps;

use App\Enums\TablerIcon;
use App\Livewire\Installer\PanelInstaller;
use Exception;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;

class DatabaseStep
{
    public const DATABASE_DRIVERS = [
        'sqlite' => 'SQLite',
        'mariadb' => 'MariaDB',
        'mysql' => 'MySQL',
        'pgsql' => 'PostgreSQL',
    ];

    public static function make(PanelInstaller $installer): Step
    {
        return Step::make('database')
            ->label(trans('installer.database.title'))
            ->columns()
            ->schema([
                ToggleButtons::make('env_database.DB_CONNECTION')
                    ->label(trans('installer.database.driver'))
                    ->hintIcon(TablerIcon::QuestionMark, trans('installer.database.driver_help'))
                    ->required()
                    ->inline()
                    ->options(self::DATABASE_DRIVERS)
                    ->default(config('database.default'))
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $set('env_database.DB_DATABASE', self::getConfiguredConnectionValue($state, 'database', 'panel'));

                        switch ($state) {
                            case 'sqlite':
                                $set('env_database.DB_HOST', null);
                                $set('env_database.DB_PORT', null);
                                $set('env_database.DB_USERNAME', null);
                                $set('env_database.DB_PASSWORD', null);
                                break;
                            case 'mariadb':
                            case 'mysql':
                                $set('env_database.DB_HOST', $get('env_database.DB_HOST') ?? self::getConfiguredConnectionValue($state, 'host', '127.0.0.1'));
                                $set('env_database.DB_USERNAME', $get('env_database.DB_USERNAME') ?? self::getConfiguredConnectionValue($state, 'username', 'pelican'));
                                $set('env_database.DB_PORT', self::getConfiguredConnectionValue($state, 'port', '3306'));
                                break;
                            case 'pgsql':
                                $set('env_database.DB_HOST', $get('env_database.DB_HOST') ?? self::getConfiguredConnectionValue($state, 'host', '127.0.0.1'));
                                $set('env_database.DB_USERNAME', $get('env_database.DB_USERNAME') ?? self::getConfiguredConnectionValue($state, 'username', 'pelican'));
                                $set('env_database.DB_PORT', self::getConfiguredConnectionValue($state, 'port', '5432'));
                                break;
                        }
                    }),
                TextInput::make('env_database.DB_DATABASE')
                    ->label(fn (Get $get) => $get('env_database.DB_CONNECTION') === 'sqlite' ? trans('installer.database.fields.path') : trans('installer.database.fields.name'))
                    ->placeholder(fn (Get $get) => $get('env_database.DB_CONNECTION') === 'sqlite' ? 'database.sqlite' : 'panel')
                    ->hintIcon(TablerIcon::QuestionMark, fn (Get $get) => $get('env_database.DB_CONNECTION') === 'sqlite' ? trans('installer.database.fields.path_help') : trans('installer.database.fields.name_help'))
                    ->required()
                    ->default(fn (Get $get) => self::getConnectionDefault($get, 'database', 'panel')),
                TextInput::make('env_database.DB_HOST')
                    ->label(trans('installer.database.fields.host'))
                    ->placeholder('127.0.0.1')
                    ->hintIcon(TablerIcon::QuestionMark, trans('installer.database.fields.host_help'))
                    ->required(fn (Get $get) => $get('env_database.DB_CONNECTION') !== 'sqlite')
                    ->default(fn (Get $get) => self::getConnectionDefault($get, 'host', '127.0.0.1'))
                    ->hidden(fn (Get $get) => $get('env_database.DB_CONNECTION') === 'sqlite'),
                TextInput::make('env_database.DB_PORT')
                    ->label(trans('installer.database.fields.port'))
                    ->placeholder('3306')
                    ->hintIcon(TablerIcon::QuestionMark, trans('installer.database.fields.port_help'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->required(fn (Get $get) => $get('env_database.DB_CONNECTION') !== 'sqlite')
                    ->default(fn (Get $get) => self::getConnectionDefault(
                        $get,
                        'port',
                        $get('env_database.DB_CONNECTION') === 'pgsql' ? '5432' : '3306',
                    ))
                    ->hidden(fn (Get $get) => $get('env_database.DB_CONNECTION') === 'sqlite'),
                TextInput::make('env_database.DB_USERNAME')
                    ->label(trans('installer.database.fields.username'))
                    ->placeholder('pelican')
                    ->hintIcon(TablerIcon::QuestionMark, trans('installer.database.fields.username_help'))
                    ->required(fn (Get $get) => $get('env_database.DB_CONNECTION') !== 'sqlite')
                    ->default(fn (Get $get) => self::getConnectionDefault($get, 'username', 'pelican'))
                    ->hidden(fn (Get $get) => $get('env_database.DB_CONNECTION') === 'sqlite'),
                TextInput::make('env_database.DB_PASSWORD')
                    ->label(trans('installer.database.fields.password'))
                    ->hintIcon(TablerIcon::QuestionMark, trans('installer.database.fields.password_help'))
                    ->password()
                    ->revealable()
                    ->hidden(fn (Get $get) => $get('env_database.DB_CONNECTION') === 'sqlite'),
            ])
            ->afterValidation(function (Get $get) use ($installer) {
                $driver = $get('env_database.DB_CONNECTION');

                throw_unless(self::testConnection($driver, $get('env_database.DB_HOST'), $get('env_database.DB_PORT'), $get('env_database.DB_DATABASE'), $get('env_database.DB_USERNAME'), $get('env_database.DB_PASSWORD')), new Halt(trans('installer.database.exceptions.connection')));

                $installer->writeToEnv('env_database');
            });
    }

    private static function getConnectionDefault(Get $get, string $key, mixed $fallback): mixed
    {
        $driver = $get('env_database.DB_CONNECTION');

        return self::getConfiguredConnectionValue($driver, $key, $fallback);
    }

    private static function getConfiguredConnectionValue(mixed $driver, string $key, mixed $fallback): mixed
    {
        if ($driver === 'sqlite') {
            return $key === 'database' ? 'database.sqlite' : null;
        }

        if (!is_string($driver) || $driver !== config('database.default')) {
            return $fallback;
        }

        return config("database.connections.{$driver}.{$key}", $fallback);
    }

    private static function testConnection(string $driver, ?string $host, null|string|int $port, ?string $database, ?string $username, ?string $password): bool
    {
        if ($driver === 'sqlite') {
            return true;
        }

        $password = self::getConnectionPassword($driver, $password);

        try {
            config()->set('database.connections._panel_install_test', [
                'driver' => $driver,
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'collation' => 'utf8mb4_unicode_ci',
                'strict' => true,
            ]);

            DB::connection('_panel_install_test')->getPdo();
        } catch (Exception $exception) {
            DB::disconnect('_panel_install_test');

            Notification::make()
                ->title(trans('installer.database.exceptions.connection'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return false;
        }

        return true;
    }

    private static function getConnectionPassword(string $driver, ?string $password): ?string
    {
        $configuredPassword = config("database.connections.{$driver}.password");
        if (($password === null || $password === '') && $driver === config('database.default') && is_string($configuredPassword)) {
            return $configuredPassword;
        }

        return $password;
    }
}
