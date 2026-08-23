<?php

namespace App\Extensions\OAuth\Schemas;

use App\Enums\TablerIcon;
use App\Extensions\OAuth\OAuthSchemaInterface;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as OAuthUser;

abstract class OAuthSchema implements OAuthSchemaInterface
{
    abstract public function getId(): string;

    public function getSocialiteProvider(): ?string
    {
        return null;
    }

    public function getServiceConfig(): array
    {
        $id = Str::upper($this->getId());

        return [
            'client_id' => env("OAUTH_{$id}_CLIENT_ID"),
            'client_secret' => env("OAUTH_{$id}_CLIENT_SECRET"),
        ];
    }

    /**
     * @return Component[]
     */
    public function getSettingsForm(): array
    {
        $id = Str::upper($this->getId());

        return [
            TextInput::make("OAUTH_{$id}_CLIENT_ID")
                ->label(trans('admin/setting.oauth.client_id'))
                ->placeholder(trans('admin/setting.oauth.client_id'))
                ->columnSpan(2)
                ->required()
                ->password()
                ->revealable()
                ->autocomplete(false)
                ->default(env("OAUTH_{$id}_CLIENT_ID")),
            TextInput::make("OAUTH_{$id}_CLIENT_SECRET")
                ->label(trans('admin/setting.oauth.client_secret'))
                ->placeholder(trans('admin/setting.oauth.client_secret'))
                ->columnSpan(2)
                ->required()
                ->password()
                ->revealable()
                ->autocomplete(false)
                ->default(env("OAUTH_{$id}_CLIENT_SECRET")),
            Toggle::make("OAUTH_{$id}_SHOULD_CREATE_MISSING_USERS")
                ->label(trans('admin/setting.oauth.create_missing_users'))
                ->columnSpan(2)
                ->inline(false)
                ->onIcon(TablerIcon::Check)
                ->offIcon(TablerIcon::X)
                ->onColor('success')
                ->offColor('danger')
                ->formatStateUsing(fn ($state) => (bool) $state)
                ->afterStateUpdated(fn ($state, Set $set) => $set("OAUTH_{$id}_SHOULD_CREATE_MISSING_USERS", (bool) $state))
                ->default(env("OAUTH_{$id}_SHOULD_CREATE_MISSING_USERS")),
            Toggle::make("OAUTH_{$id}_SHOULD_LINK_MISSING_USERS")
                ->label(trans('admin/setting.oauth.link_missing_users'))
                ->columnSpan(2)
                ->inline(false)
                ->onIcon(TablerIcon::Check)
                ->offIcon(TablerIcon::X)
                ->onColor('success')
                ->offColor('danger')
                ->formatStateUsing(fn ($state) => (bool) $state)
                ->afterStateUpdated(fn ($state, Set $set) => $set("OAUTH_{$id}_SHOULD_LINK_MISSING_USERS", (bool) $state))
                ->default(env("OAUTH_{$id}_SHOULD_LINK_MISSING_USERS")),
        ];
    }

    /**
     * @return Step[]
     */
    public function getSetupSteps(): array
    {
        return [
            Step::make('OAuth Config')
                ->columns(4)
                ->schema($this->getSettingsForm()),
        ];
    }

    public function getName(): string
    {
        return Str::title($this->getId());
    }

    public function getConfigKey(): string
    {
        $id = Str::upper($this->getId());

        return "OAUTH_{$id}_ENABLED";
    }

    public function getIcon(): null|string|BackedEnum
    {
        return null;
    }

    public function getHexColor(): ?string
    {
        return null;
    }

    public function isEnabled(): bool
    {
        return (bool) env($this->getConfigKey(), false);
    }

    public function shouldCreateMissingUser(OAuthUser $user): bool
    {
        $id = Str::upper($this->getId());

        return (bool) env("OAUTH_{$id}_SHOULD_CREATE_MISSING_USERS", false);
    }

    public function shouldLinkMissingUser(User $user, OAuthUser $oauthUser): bool
    {
        $id = Str::upper($this->getId());

        return (bool) env("OAUTH_{$id}_SHOULD_LINK_MISSING_USERS", false);
    }
}
