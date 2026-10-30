<?php

namespace App\Providers\Filament;

use App\Enums\TablerIcon;
use Boquizo\FilamentLogViewer\FilamentLogViewerPlugin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Panel;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = parent::panel($panel)
            ->id('app')
            ->default()
            ->breadcrumbs(false)
            ->navigation(false)
            ->topbar(true)
            ->userMenuItems([
                Action::make('to_admin')
                    ->label(trans('profile.admin'))
                    ->url(fn () => Filament::getPanel('admin')->getUrl())
                    ->icon(TablerIcon::ArrowForward)
                    ->visible(fn () => user()?->canAccessPanel(Filament::getPanel('admin'))),
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->plugins([
                FilamentLogViewerPlugin::make()
                    ->authorize(false),
            ]);

        return $panel;
    }
}
