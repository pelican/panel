<?php

namespace App\Extensions\OAuth\Schemas;

use App\Enums\TablerIcon;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

final class GithubSchema extends OAuthSchema
{
    public function getId(): string
    {
        return 'github';
    }

    public function getSetupSteps(): array
    {
        return array_merge([
            Step::make('Register new GitHub OAuth App')
                ->schema([
                    TextEntry::make('create_application')
                        ->hiddenLabel()
                        ->state(new HtmlString(Blade::render('<p>Visit the <x-filament::link href="https://github.com/settings/developers" target="_blank">GitHub Developer Dashboard</x-filament::link>, go to <b>OAuth Apps</b> and click on <b>New OAuth App</b>.</p><p>Enter an <b>Application name</b> (e.g. your panel name), set <b>Homepage URL</b> to your panel url and enter the below url as <b>Authorization callback URL</b>.</p>'))),
                    TextInput::make('_noenv_callback')
                        ->label(trans('admin/setting.oauth.auth_url'))
                        ->dehydrated()
                        ->disabled()
                        ->hintCopy()
                        ->default(fn () => url('/auth/oauth/callback/github')),
                    TextEntry::make('register_application')
                        ->hiddenLabel()
                        ->state(new HtmlString('<p>When you filled all fields click on <b>Register application</b>.</p>')),
                ]),
            Step::make('Create Client Secret')
                ->schema([
                    TextEntry::make('create_client_secret')
                        ->hiddenLabel()
                        ->state(new HtmlString('<p>Once you registered your app, generate a new <b>Client Secret</b>.</p><p>You will also need the <b>Client ID</b>.</p>')),
                ]),
        ], parent::getSetupSteps());
    }

    public function getIcon(): BackedEnum
    {
        return TablerIcon::BrandGithubFilled;
    }

    public function getHexColor(): string
    {
        return '#4078c0';
    }
}
