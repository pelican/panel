<?php

namespace App\Livewire\Installer\Steps;

use App\Enums\DatabaseDriver;
use App\Enums\TablerIcon;
use App\Livewire\Installer\PanelInstaller;
use App\Services\Environment\InstallationHealthService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;

class DatabaseStep
{
    /**
     * Build the database configuration step and validate it before persistence.
     */
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
                    ->options(DatabaseDriver::options())
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
                    ->label(fn (Get $get) => $get('env_database.DB_CONNECTION') === DatabaseDriver::SQLite->value ? trans('installer.database.fields.path') : trans('installer.database.fields.name'))
                    ->placeholder(fn (Get $get) => $get('env_database.DB_CONNECTION') === DatabaseDriver::SQLite->value ? 'database.sqlite' : 'panel')
                    ->hintIcon(TablerIcon::QuestionMark, fn (Get $get) => $get('env_database.DB_CONNECTION') === DatabaseDriver::SQLite->value ? trans('installer.database.fields.path_help') : trans('installer.database.fields.name_help'))
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
                    ->hidden(fn (Get $get) => $get('env_database.DB_CONNECTION') === DatabaseDriver::SQLite->value),
            ])
            ->afterValidation(function (Get $get) use ($installer) {
                $health = app(InstallationHealthService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions
                $driver = DatabaseDriver::from($get('env_database.DB_CONNECTION'));
                $extensionResult = $health->databaseDriverExtension($driver);

                if ($health->hasFailures([$extensionResult])) {
                    Notification::make()
                        ->title(trans('installer.database.exceptions.extension'))
                        ->body($extensionResult->getNotificationMessage())
                        ->danger()
                        ->send();

                    throw new Halt($extensionResult->getNotificationMessage());
                }

                $connectionResult = $health->databaseConnection($driver, [
                    'host' => $get('env_database.DB_HOST'),
                    'port' => $get('env_database.DB_PORT'),
                    'database' => $get('env_database.DB_DATABASE'),
                    'username' => $get('env_database.DB_USERNAME'),
                    'password' => self::getConnectionPassword(
                        $driver->value,
                        $get('env_database.DB_PASSWORD'),
                    ),
                ]);

                if ($health->hasFailures([$connectionResult])) {
                    Notification::make()
                        ->title(trans('installer.database.exceptions.connection'))
                        ->body($connectionResult->getNotificationMessage())
                        ->danger()
                        ->send();

                    throw new Halt($connectionResult->getNotificationMessage());
                }

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
        if ($driver === DatabaseDriver::SQLite->value) {
            return $key === 'database' ? 'database.sqlite' : null;
        }

        if (!is_string($driver) || $driver !== config('database.default')) {
            return $fallback;
        }

        return config("database.connections.{$driver}.{$key}", $fallback);
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
