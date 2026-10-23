<?php

namespace App\Services\Helpers;

use App\Contracts\Plugins\HasTheme;
use App\Enums\CustomizationKey;
use App\Models\Plugin;
use App\Traits\EnvironmentWriterTrait;
use Closure;
use Filament\Facades\Filament;
use Filament\FontProviders\BunnyFontProvider;
use Filament\Panel;
use Filament\Support\Facades\FilamentColor;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Vite;

class ThemeService
{
    use EnvironmentWriterTrait;

    public const Default = 'default';

    public const None = 'none';

    /** @return array<string, string> */
    public function getThemes(): array
    {
        return Plugin::query()
            ->themes()
            ->get()
            ->filter(fn (Plugin $plugin) => $plugin->isSwitchableTheme())
            ->mapWithKeys(fn (Plugin $plugin) => [$plugin->id => $plugin->name])
            ->all();
    }

    /** @return array<string, string> */
    public function getThemeOptions(): array
    {
        return [self::None => trans('profile.no_theme')] + $this->getThemes();
    }

    /** @return array<string, string> */
    public function getUserThemeOptions(): array
    {
        return [self::Default => trans('profile.default_theme')] + $this->getThemeOptions();
    }

    public function getActiveThemeId(): ?string
    {
        $themeId = $this->isForced() ? self::Default : $this->getSelectedOption();

        if ($themeId === self::Default) {
            $themeId = config('panel.filament.default-theme');
        }

        return is_string($themeId) && $this->isRegisteredTheme($themeId) ? $themeId : null;
    }

    public function isForced(): bool
    {
        return (bool) config('panel.filament.force-theme');
    }

    public function canChooseTheme(): bool
    {
        return !$this->isForced() && $this->getThemes() !== [];
    }

    public function getSelectedOption(): string
    {
        $themeId = user()?->getCustomization(CustomizationKey::Theme);

        if ($themeId === self::None) {
            return self::None;
        }

        return is_string($themeId) && $this->isRegisteredTheme($themeId) ? $themeId : self::Default;
    }

    public function isActive(string $themeId): bool
    {
        return $this->getActiveThemeId() === $themeId;
    }

    public function getDefaultThemeId(): ?string
    {
        $themeId = config('panel.filament.default-theme');

        return is_string($themeId) && $themeId !== '' ? $themeId : null;
    }

    public function setDefaultTheme(string $themeId, bool $force = false): void
    {
        $this->writeToEnvironment([
            'FILAMENT_DEFAULT_THEME' => $themeId,
            'FILAMENT_FORCE_THEME' => $force,
        ]);
    }

    /** Applies each theme on the panel, gated so only the active one renders. */
    public function registerThemes(Panel $panel): void
    {
        foreach ($panel->getPlugins() as $theme) {
            if (!$theme instanceof HasTheme) {
                continue;
            }

            $whenActive = fn (Closure $hook): Closure => function (Container $container, array $data = [], array $scopes = []) use ($theme, $hook): string {
                if (!$this->isActive($theme->getId())) {
                    return '';
                }

                return (string) $container->call($hook, ['data' => $data, 'scopes' => $scopes]);
            };

            $panel->renderHook(PanelsRenderHook::STYLES_AFTER, $whenActive(fn (Vite $vite): string => filled($entry = $theme->getThemeViteEntry()) ? $vite($entry)->toHtml() : ''));

            $panel->renderHook(PanelsRenderHook::HEAD_END, $whenActive(function (BunnyFontProvider $fontProvider) use ($theme): string {
                $font = $theme->getThemeFont();

                if (blank($font)) {
                    return '';
                }

                return $fontProvider->getHtml($font)->toHtml() . "<style>:root{--font-family:'" . e($font) . "';}</style>";
            }));

            foreach ($theme->getThemeRenderHooks() as $name => $hook) {
                $panel->renderHook($name, $whenActive($hook));
            }

            // Registered on boot so the theme's colors override the panel's own.
            $panel->bootUsing(fn () => FilamentColor::register(fn (): array => $this->isActive($theme->getId()) ? $theme->getThemeColors() : []));
        }
    }

    private function isRegisteredTheme(string $themeId): bool
    {
        $panel = $this->getPanel();

        return $panel->hasPlugin($themeId) && $panel->getPlugin($themeId) instanceof HasTheme;
    }

    private function getPanel(): Panel
    {
        return Filament::getCurrentOrDefaultPanel();
    }
}
