<?php

namespace App\Livewire\Installer\Steps;

use App\Enums\TablerIcon;
use App\Livewire\Installer\PanelInstaller;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;

class EnvironmentStep
{
    public static function make(PanelInstaller $installer): Step
    {
        return Step::make('environment')
            ->label(trans('installer.environment.title'))
            ->columns()
            ->schema([
                TextInput::make('env_general.APP_NAME')
                    ->label(trans('installer.environment.fields.app_name'))
                    ->hintIcon(TablerIcon::QuestionMark, trans('installer.environment.fields.app_name_help'))
                    ->required()
                    ->default(config('app.name')),
                TextInput::make('env_general.APP_URL')
                    ->label(trans('installer.environment.fields.app_url'))
                    ->hintIcon(TablerIcon::QuestionMark, trans('installer.environment.fields.app_url_help'))
                    ->required()
                    ->default(config('app.url')),
                Fieldset::make('admin_user')
                    ->label(trans('installer.environment.fields.account.section'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('user.email')
                            ->label(trans('installer.environment.fields.account.email'))
                            ->required()
                            ->email()
                            ->placeholder('admin@example.com'),
                        TextInput::make('user.username')
                            ->label(trans('installer.environment.fields.account.username'))
                            ->required()
                            ->placeholder('admin'),
                        TextInput::make('user.password')
                            ->label(trans('installer.environment.fields.account.password'))
                            ->required()
                            ->password()
                            ->revealable(),
                    ]),
            ])
            ->afterValidation(fn () => $installer->writeToEnv('env_general'));
    }
}
