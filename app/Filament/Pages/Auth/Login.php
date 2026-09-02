<?php

namespace App\Filament\Pages\Auth;

use App\Enums\TablerIcon;
use App\Extensions\Captcha\CaptchaService;
use App\Extensions\OAuth\OAuthService;
use App\Models\User;
use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory as IconFactory;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected OAuthService $oauthService;

    protected CaptchaService $captchaService;

    protected IconFactory $iconFactory;

    protected Request $request;

    public function boot(OAuthService $oauthService, CaptchaService $captchaService, IconFactory $iconFactory, Request $request): void
    {
        $this->oauthService = $oauthService;
        $this->captchaService = $captchaService;
        $this->iconFactory = $iconFactory;
        $this->request = $request;
    }

    public function mount(): void
    {
        parent::mount();

        if ($message = session()->pull('authenticatePasskey::message')) {
            Notification::make()->title($message)->danger()->send();
        }
    }

    public function form(Schema $schema): Schema
    {
        if (config('auth.disable_password_login', false)) {
            return $schema->components([
                $this->getOAuthFormComponent(),
                $this->getPasskeyFormComponent(),
            ]);
        }

        $components = [
            $this->getLoginFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
            $this->getOAuthFormComponent(),
            $this->getPasskeyFormComponent(),
        ];

        if ($captchaComponent = $this->getCaptchaComponent()) {
            $components[] = $captchaComponent
                ->hidden(fn () => filled($this->userUndertakingMultiFactorAuthentication));
        }

        return $schema->components($components);
    }

    private function getCaptchaComponent(): ?Component
    {
        return $this->captchaService->getActiveSchema()?->getFormComponent();
    }

    protected function getPasskeyFormComponent(): Component
    {
        return Actions::make([
            Action::make('passkey')
                ->label(trans('passkeys.authenticate_using_passkey'))
                ->icon(TablerIcon::Key->value)
                ->color('gray')
                ->alpineClickHandler('window.authenticateWithPasskey()')
                ->extraAttributes(['type' => 'button']),
        ])->fullWidth()->hidden(fn () => !$this->request->isSecure());
    }

    protected function throwFailureValidationException(): never
    {
        $this->dispatch('reset-captcha');

        $login = $this->data['login'] ?? null;
        $password = $this->data['password'] ?? null;
        if (is_string($login) && is_string($password)) {
            $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            $user = User::query()->where($field, mb_strtolower($login))->first();

            if ($user?->isSuspended() && Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'data.login' => User::suspensionMessage(),
                ]);
            }
        }

        throw ValidationException::withMessages([
            'data.login' => trans('filament-panels::auth/pages/login.messages.failed')]);
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(trans('filament-panels::auth/pages/login.title'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $component */
        $component = parent::getPasswordFormComponent();

        return $component->extraInputAttributes(['tabindex' => 2]);
    }

    protected function getOAuthFormComponent(): Component
    {
        $actions = [];

        $oauthSchemas = $this->oauthService->getEnabled();

        foreach ($oauthSchemas as $schema) {

            $id = $schema->getId();

            $color = $schema->getHexColor();
            $color = is_string($color) ? Color::hex($color) : null;

            $icon = $schema->getIcon();
            if (is_string($icon)) {
                try {
                    $this->iconFactory->svg($icon);
                } catch (SvgNotFound) {
                    $icon = null;
                }
            }

            $actions[] = Action::make("oauth_$id")
                ->label($schema->getName())
                ->icon($icon)
                ->color($color)
                ->url(route('auth.oauth.redirect', ['driver' => $id], false));
        }

        return Actions::make($actions)->alignment(fn () => config('auth.disable_password_login', false) ? Alignment::Center : null);
    }

    protected function getFormActions(): array
    {
        return config('auth.disable_password_login', false) ? [] : parent::getFormActions();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        if (config('auth.disable_password_login', false)) {
            throw ValidationException::withMessages([
                'data.login' => trans('auth.password_login_disabled'),
            ]);
        }

        $loginType = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $loginType => mb_strtolower($data['login']),
            'password' => $data['password'],
        ];
    }
}
