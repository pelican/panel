<?php

namespace App\Providers\Filament;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\App\Resources\Servers\Pages\ListServers;
use App\Http\Middleware\Activity\ServerSubject;
use App\Models\Server;
use App\Services\Helpers\PluginService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class ServerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = parent::panel($panel)
            ->id('server')
            ->path('server')
            ->homeUrl(fn () => Filament::getPanel('app')->getUrl())
            ->tenant(Server::class, 'uuid_short')
            ->userMenuItems([
                Action::make('to_serverList')
                    ->label(trans('profile.server_list'))
                    ->icon(TablerIcon::BrandDocker)
                    ->url(fn () => ListServers::getUrl(panel: 'app')),
                Action::make('to_admin')
                    ->label(trans('profile.admin'))
                    ->icon(TablerIcon::ArrowForward)
                    ->url(fn () => Filament::getPanel('admin')->getUrl())
                    ->visible(fn () => user()?->canAccessPanel(Filament::getPanel('admin'))),
            ])
            ->navigationItems([
                NavigationItem::make(trans('server/console.open_in_admin'))
                    ->url(fn () => EditServer::getUrl(['record' => Filament::getTenant()], panel: 'admin'))
                    ->visible(fn () => user()?->canAccessPanel(Filament::getPanel('admin')) && user()->can('view server', Filament::getTenant()))
                    ->icon(TablerIcon::ArrowBack)
                    ->sort(99),
            ])
            // Persisted across SPA navigation so file uploads keep running when switching pages.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => Blade::render('@persist("file-upload-manager") @livewire(\App\Livewire\FileUploadManager::class) @endpersist'),
            )
            ->discoverResources(in: app_path('Filament/Server/Resources'), for: 'App\\Filament\\Server\\Resources')
            ->discoverPages(in: app_path('Filament/Server/Pages'), for: 'App\\Filament\\Server\\Pages')
            ->discoverWidgets(in: app_path('Filament/Server/Widgets'), for: 'App\\Filament\\Server\\Widgets')
            ->tenantMiddleware([
                ServerSubject::class,
            ]);

        /** @var PluginService $pluginService */
        $pluginService = app(PluginService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $pluginService->loadPanelPlugins($panel);

        return $panel;
    }
}
