<?php

namespace App\Traits\Filament;

use App\Services\Helpers\ThemeService;
use Closure;
use Filament\FontProviders\BunnyFontProvider;
use Filament\Panel;
use Filament\Support\Facades\FilamentColor;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Vite;

/** @phpstan-ignore trait.unused */
trait IsTheme
{
    /**
     * @return array<string, array<int|string, string|int>|string>
     */
    public function getThemeColors(): array
    {
        return [];
    }

    public function getThemeFont(): ?string
    {
        return null;
    }

    /**
     * @return string|array<string>|null
     */
    public function getThemeViteEntry(): string|array|null
    {
        return null;
    }

    /**
     * @return array<string, Closure>
     */
    public function getThemeRenderHooks(): array
    {
        return [];
    }

    public function register(Panel $panel): void
    {
        $this->registerTheme($panel);
    }

    public function boot(Panel $panel): void
    {
        $this->bootTheme();
    }

    protected function registerTheme(Panel $panel): void
    {
        $panel->renderHook(PanelsRenderHook::STYLES_AFTER, $this->whenActive(fn (Vite $vite): string => filled($entry = $this->getThemeViteEntry()) ? $vite($entry)->toHtml() : ''));

        $panel->renderHook(PanelsRenderHook::HEAD_END, $this->whenActive(function (BunnyFontProvider $fontProvider): string {
            $font = $this->getThemeFont();

            if (blank($font)) {
                return '';
            }

            return $fontProvider->getHtml($font)->toHtml() . "<style>:root{--font-family:'" . e($font) . "';}</style>";
        }));

        foreach ($this->getThemeRenderHooks() as $name => $hook) {
            $panel->renderHook($name, $this->whenActive($hook));
        }
    }

    protected function bootTheme(): void
    {
        FilamentColor::register(fn (ThemeService $themeService): array => $themeService->isActive($this->getId()) ? $this->getThemeColors() : []);
    }

    protected function whenActive(Closure $hook): Closure
    {
        return function (ThemeService $themeService, Container $container, array $data = [], array $scopes = []) use ($hook): string {
            if (!$themeService->isActive($this->getId())) {
                return '';
            }

            return (string) $container->call($hook, ['data' => $data, 'scopes' => $scopes]);
        };
    }
}
