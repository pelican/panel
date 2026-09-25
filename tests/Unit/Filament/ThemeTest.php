<?php

namespace App\Tests\Unit\Filament;

use App\Contracts\Plugins\HasTheme;
use App\Enums\CustomizationKey;
use App\Models\User;
use App\Services\Helpers\ThemeService;
use App\Tests\TestCase;
use App\Traits\Filament\IsTheme;
use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Auth;

class ThemeTest extends TestCase
{
    private function panel(): Panel
    {
        $panel = Panel::make()
            ->id('testing')
            ->plugin(new FakeTheme('alpha', ['primary' => Color::Rose, 'blurple' => Color::Purple], 'Alpha Sans'))
            ->plugin(new FakeTheme('beta', ['primary' => Color::Lime]));

        Filament::setCurrentPanel($panel);

        return $panel;
    }

    private function actingAsUserWithTheme(?string $theme): void
    {
        $user = new User();
        $user->customization = $theme === null ? [] : [CustomizationKey::Theme->value => $theme];

        Auth::guard(config('auth.defaults.guard', 'web'))->setUser($user);
    }

    private function activeThemeId(): ?string
    {
        return $this->app->make(ThemeService::class)->getActiveThemeId();
    }

    public function test_it_falls_back_to_the_global_default_when_the_user_has_not_picked_one(): void
    {
        config()->set('panel.filament.default-theme', 'beta');

        $this->panel();
        $this->actingAsUserWithTheme(null);

        $this->assertSame('beta', $this->activeThemeId());
    }

    public function test_the_users_theme_wins_over_the_global_default(): void
    {
        config()->set('panel.filament.default-theme', 'beta');

        $this->panel();
        $this->actingAsUserWithTheme('alpha');

        $this->assertSame('alpha', $this->activeThemeId());
    }

    public function test_a_user_can_opt_out_of_the_global_default(): void
    {
        config()->set('panel.filament.default-theme', 'beta');

        $this->panel();
        $this->actingAsUserWithTheme(ThemeService::None);

        $this->assertNull($this->activeThemeId());
    }

    public function test_an_uninstalled_theme_is_ignored(): void
    {
        $this->panel();
        $this->actingAsUserWithTheme('gone');

        $this->assertNull($this->activeThemeId());
    }

    public function test_only_the_active_themes_colors_reach_filament(): void
    {
        config()->set('panel.filament.default-theme', 'beta');

        $panel = $this->panel();
        $this->actingAsUserWithTheme(null);

        FilamentColor::register(['primary' => Color::Blue, 'gray' => Color::Zinc]);

        $panel->boot();

        $colors = FilamentColor::getColors();

        $this->assertSame(Color::Lime[500], $colors['primary'][500]);
        $this->assertArrayNotHasKey('blurple', $colors);
        $this->assertSame(Color::Zinc[500], $colors['gray'][500]);
    }

    public function test_the_panel_keeps_its_own_colors_when_no_theme_is_active(): void
    {
        config()->set('panel.filament.default-theme', ThemeService::None);

        $panel = $this->panel()->colors(['primary' => Color::Blue]);
        $this->actingAsUserWithTheme(null);

        $panel->boot();

        $this->assertSame(Color::Blue[500], FilamentColor::getColors()['primary'][500]);
    }

    public function test_only_the_active_theme_renders_its_hooks_and_font(): void
    {
        config()->set('panel.filament.default-theme', 'alpha');

        $panel = $this->panel();
        $this->actingAsUserWithTheme(null);

        $panel->boot();

        $head = (string) FilamentView::renderHook('panels::head.end');

        $this->assertStringContainsString('<!--alpha-->', $head);
        $this->assertStringNotContainsString('<!--beta-->', $head);
        $this->assertStringContainsString("--font-family:'Alpha Sans'", $head);
    }
}

final class FakeTheme implements HasTheme
{
    use IsTheme;

    /** @param array<string, array<int|string, string|int>|string> $colors */
    public function __construct(
        private readonly string $id,
        private readonly array $colors,
        private readonly ?string $font = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getThemeColors(): array
    {
        return $this->colors;
    }

    public function getThemeFont(): ?string
    {
        return $this->font;
    }

    /** @return array<string, Closure> */
    public function getThemeRenderHooks(): array
    {
        return ['panels::head.end' => fn (): string => "<!--{$this->id}-->"];
    }
}
