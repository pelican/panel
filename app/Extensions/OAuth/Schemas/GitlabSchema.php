<?php

namespace App\Extensions\OAuth\Schemas;

use App\Enums\TablerIcon;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

final class GitlabSchema extends OAuthSchema
{
    public function getId(): string
    {
        return 'gitlab';
    }

    public function getServiceConfig(): array
    {
        return array_merge(parent::getServiceConfig(), [
            'host' => config('extensions.OAUTH_GITLAB_HOST'),
        ]);
    }

    public function getSettingsForm(): array
    {
        return array_merge(parent::getSettingsForm(), [
            TextInput::make('OAUTH_GITLAB_HOST')
                ->label('Custom Host')
                ->placeholder('Only set a custom host if you are self hosting gitlab')
                ->columnSpan(2)
                ->url()
                ->autocomplete(false)
                ->default(config('extensions.OAUTH_GITLAB_HOST')),
        ]);
    }

    public function getSetupSteps(): array
    {
        return array_merge([
            Step::make('Register new Gitlab OAuth App')
                ->schema([
                    TextEntry::make('register_application')
                        ->hiddenLabel()
                        ->state(new HtmlString(Blade::render('Check out the <x-filament::link href="https://docs.gitlab.com/integration/oauth_provider/" target="_blank">Gitlab docs</x-filament::link> on how to create the oauth app.'))),
                    TextInput::make('_noenv_callback')
                        ->label('Redirect URI')
                        ->dehydrated()
                        ->disabled()
                        ->hintCopy()
                        ->default(fn () => url('/auth/oauth/callback/gitlab')),
                ]),
        ], parent::getSetupSteps());
    }

    public function getIcon(): BackedEnum
    {
        return TablerIcon::BrandGitlab;
    }

    public function getHexColor(): string
    {
        return '#fca326';
    }
}
