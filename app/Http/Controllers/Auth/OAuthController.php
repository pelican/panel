<?php

namespace App\Http\Controllers\Auth;

use App\Extensions\OAuth\OAuthSchemaInterface;
use App\Extensions\OAuth\OAuthService;
use App\Filament\Pages\Auth\EditProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Users\UserCreationService;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\User as OAuthUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class OAuthController extends Controller
{
    public function __construct(
        private readonly UserCreationService $userCreation,
        private readonly OAuthService $oauthService,
    ) {}

    /**
     * Redirect user to the OAuth provider
     */
    public function redirect(string $driver): SymfonyRedirectResponse|RedirectResponse
    {
        if (!$this->oauthService->get($driver)->isEnabled()) {
            return redirect()->route('auth.login');
        }

        return Socialite::driver($driver)->redirect();
    }

    /**
     * Callback from OAuth provider.
     */
    public function callback(Request $request, string $driver): RedirectResponse
    {
        $driver = $this->oauthService->get($driver);

        if (!$driver || !$driver->isEnabled()) {
            return redirect()->route('auth.login');
        }

        // Check for errors (https://www.oauth.com/oauth2-servers/server-side-apps/possible-errors/)
        if ($request->input('error')) {
            report($request->input('error_description') ?? $request->input('error'));

            return $this->errorRedirect($request->input('error'));
        }

        $oauthUser = Socialite::driver($driver->getId())->user();

        if ($request->user()) {
            $this->oauthService->linkUser($request->user(), $driver, $oauthUser);

            return redirect(EditProfile::getUrl(['tab' => 'oauth::data::tab'], panel: 'app'));
        }

        $user = User::whereJsonContains('oauth->'. $driver->getId(), $oauthUser->getId())->first();
        if ($user) {
            return $this->loginUser($user);
        }

        return $this->handleMissingUser($driver, $oauthUser);
    }

    private function handleMissingUser(OAuthSchemaInterface $driver, OAuthUser $oauthUser): RedirectResponse
    {
        $email = $oauthUser->getEmail();

        if (!$email) {
            return $this->errorRedirect('No email was linked to your account on the OAuth provider.');
        }

        if (isset($oauthUser->email_verified) && !filter_var($oauthUser->email_verified, FILTER_VALIDATE_BOOLEAN)) {
            return $this->errorRedirect('Email not verified on OAuth provider.');
        }

        $user = User::whereEmail($email)->first();
        if ($user) {
            if (!$driver->shouldLinkMissingUser($user, $oauthUser)) {
                return $this->errorRedirect();
            }

            $user = $this->oauthService->linkUser($user, $driver, $oauthUser);
        } else {
            if (!$driver->shouldCreateMissingUser($oauthUser)) {
                return $this->errorRedirect();
            }

            try {
                $user = $this->userCreation->handle([
                    'username' => $oauthUser->getNickname(),
                    'email' => $email,
                    'oauth' => [
                        $driver->getId() => $oauthUser->getId(),
                    ],
                ]);
            } catch (Exception $exception) {
                report($exception);

                return $this->errorRedirect();
            }
        }

        return $this->loginUser($user);
    }

    private function loginUser(User $user): RedirectResponse
    {
        if ($user->isSuspended()) {
            return $this->errorRedirect(User::suspensionMessage());
        }

        auth()->guard()->login($user, true);

        return redirect('/');
    }

    private function errorRedirect(?string $error = null): RedirectResponse
    {
        Notification::make()
            ->title($error ? 'Something went wrong' : 'No linked User found')
            ->body($error)
            ->danger()
            ->persistent()
            ->send();

        return redirect()->route('auth.login');
    }
}
