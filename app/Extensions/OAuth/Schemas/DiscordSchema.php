<?php

namespace App\Extensions\OAuth\Schemas;

use App\Enums\TablerIcon;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Laravel\Socialite\Contracts\User as OAuthUser;
use SocialiteProviders\Discord\Provider;

final class DiscordSchema extends OAuthSchema
{
    public function getId(): string
    {
        return 'discord';
    }

    public function getSocialiteProvider(): string
    {
        return Provider::class;
    }

    public function getSetupSteps(): array
    {
        return array_merge([
            Step::make('Register new Discord OAuth App')
                ->schema([
                    TextEntry::make('create_application')
                        ->hiddenLabel()
                        ->state(new HtmlString(Blade::render('<p>Visit the <x-filament::link href="https://discord.com/developers/applications" target="_blank">Discord Developer Portal</x-filament::link> and click on <b>New Application</b>. Enter a <b>Name</b> (e.g. your panel name) and click on <b>Create</b>.</p><p>Copy the <b>Client ID</b> and the <b>Client Secret</b> from the OAuth2 tab, you will need them in the final step.</p>'))),
                    TextEntry::make('set_redirect')
                        ->hiddenLabel()
                        ->state(new HtmlString('<p>Under <b>Redirects</b> add the below URL.</p>')),
                    TextInput::make('_noenv_callback')
                        ->label(trans('admin/setting.oauth.redirect'))
                        ->dehydrated()
                        ->disabled()
                        ->hintCopy()
                        ->formatStateUsing(fn () => url('/auth/oauth/callback/discord')),
                ]),
        ], parent::getSetupSteps());
    }

    public function getIcon(): BackedEnum
    {
        return TablerIcon::BrandDiscordFilled;
    }

    public function getHexColor(): string
    {
        return '#5865F2';
    }

    public function shouldLinkMissingUser(User $user, OAuthUser $oauthUser): bool
    {
        // Discord reports email verification as `verified` instead of `email_verified`
        return data_get($oauthUser, 'verified') === true && parent::shouldLinkMissingUser($user, $oauthUser);
    }
}
