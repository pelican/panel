<?php

namespace App\Providers\Filament;

use App\Enums\CustomizationKey;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Http\Controllers\UpdateThemeController;
use App\Http\Middleware\LanguageMiddleware;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Http\Middleware\SetSecurityHeaders;
use App\Services\Helpers\PluginService;
use App\Services\Helpers\ThemeService;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider as BasePanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

abstract class PanelProvider extends BasePanelProvider
{
    public function register(): void
    {
        Filament::registerPanel(function (): Panel {
            $panel = $this->panel(Panel::make());

            $this->app->make(PluginService::class)->loadPanelPlugins($panel);

            return $panel;
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->spa(fn () => !request()->routeIs('filament.server.pages.console'))
            ->spaUrlExceptions([
                '*/oauth/redirect/*',
            ])
            ->databaseNotifications()
            ->brandName(config('app.name', 'Pelican'))
            ->brandLogo(config('app.logo'))
            ->brandLogoHeight('2rem')
            ->favicon(config('app.favicon', '/pelican.ico'))
            ->topNavigation(function () {
                $navigationType = user()?->getCustomization(CustomizationKey::TopNavigation);

                return $navigationType === 'topbar' || $navigationType === true;
            })
            ->topbar(function () {
                $navigationType = user()?->getCustomization(CustomizationKey::TopNavigation);

                return $navigationType === 'topbar' || $navigationType === 'mixed' || $navigationType === true;
            })
            ->maxContentWidth(config('panel.filament.display-width', 'screen-2xl'))
            ->profile(EditProfile::class, false)
            ->userMenuItems([
                'profile' => fn (Action $action) => $action
                    ->url(fn () => EditProfile::getUrl(panel: 'app')),
            ])
            ->authenticatedRoutes(fn () => Route::post('theme', UpdateThemeController::class)->name('theme'))
            ->renderHook(PanelsRenderHook::USER_MENU_PROFILE_AFTER, fn (ThemeService $themeService) => $themeService->getThemes() === [] ? '' : view('filament.components.theme-select', [
                'themes' => $themeService->getThemeOptions(),
                'selected' => $themeService->getSelectedOption(),
            ]))
            ->login(Login::class)
            ->passwordReset()
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
                EmailAuthentication::make(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                LanguageMiddleware::class,
                SetSecurityHeaders::class,
                RedirectIfNotInstalled::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireTwoFactorAuthentication::class,
            ]);
    }
}
