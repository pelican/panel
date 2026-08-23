<?php

namespace App\Extensions\OAuth\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use SocialiteProviders\Authentik\Provider;

final class AuthentikSchema extends OAuthSchema
{
    public function getId(): string
    {
        return 'authentik';
    }

    public function getSocialiteProvider(): string
    {
        return Provider::class;
    }

    public function getServiceConfig(): array
    {
        return array_merge(parent::getServiceConfig(), [
            'base_url' => env('OAUTH_AUTHENTIK_BASE_URL'),
        ]);
    }

    public function getSetupSteps(): array
    {
        return array_merge([
            Step::make('Create Authentik Application')
                ->schema([
                    TextEntry::make('create_application')
                        ->hiddenLabel()
                        ->state(new HtmlString(Blade::render('<p>On your Authentik dashboard select <b>Applications</b>, then select <b>Create with Provider</b>.</p><p>On the creation step select <b>OAuth2/OpenID Provider</b> and on the configure step set <b>Redirect URIs/Origins</b> to the value below.</p>'))),
                    TextInput::make('_noenv_callback')
                        ->label('Callback URL')
                        ->dehydrated()
                        ->disabled()
                        ->hintCopy()
                        ->default(fn () => url('/auth/oauth/callback/authentik')),
                ]),
        ], parent::getSetupSteps());
    }

    public function getSettingsForm(): array
    {
        return array_merge(parent::getSettingsForm(), [
            TextInput::make('OAUTH_AUTHENTIK_BASE_URL')
                ->label('Base URL')
                ->placeholder('Base URL')
                ->columnSpan(2)
                ->required()
                ->url()
                ->autocomplete(false)
                ->default(env('OAUTH_AUTHENTIK_BASE_URL')),
            TextInput::make('OAUTH_AUTHENTIK_DISPLAY_NAME')
                ->label('Display Name')
                ->placeholder('Display Name')
                ->autocomplete(false)
                ->default(env('OAUTH_AUTHENTIK_DISPLAY_NAME', 'Authentik')),
            ColorPicker::make('OAUTH_AUTHENTIK_DISPLAY_COLOR')
                ->label('Display Color')
                ->placeholder('#fd4b2d')
                ->default(env('OAUTH_AUTHENTIK_DISPLAY_COLOR', '#fd4b2d'))
                ->hex(),
        ]);
    }

    public function getName(): string
    {
        return (string) env('OAUTH_AUTHENTIK_DISPLAY_NAME', 'Authentik');
    }

    public function getHexColor(): string
    {
        return (string) env('OAUTH_AUTHENTIK_DISPLAY_COLOR', '#fd4b2d');
    }
}
