<?php

namespace App\Filament\Admin\Resources\Plugins\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Plugins\PluginResource;
use App\Filament\Components\Actions\ReportPluginIssueAction;
use App\Jobs\Plugin\InstallPlugin;
use App\Models\Plugin;
use App\Services\Helpers\PluginHubService;
use App\Services\Helpers\PluginService;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Browse and install free plugins from the Pelican Hub without leaving the panel.
 */
class BrowsePlugins extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PluginResource::class;

    public bool $hubUnreachable = false;

    private PluginHubService $hub;

    public function boot(PluginHubService $hub): void
    {
        $this->hub = $hub;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) user()?->can('create', Plugin::class);
    }

    public function getTitle(): string|Htmlable
    {
        return trans('admin/plugin.hub.title');
    }

    public function getBreadcrumb(): string
    {
        return trans('admin/plugin.hub.browse');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(trans('admin/plugin.hub.back'))
                ->icon(TablerIcon::ArrowLeft)
                ->color('gray')
                ->url(ListPlugins::getUrl()),
            Action::make('open_hub')
                ->label(trans('admin/plugin.hub.open_hub'))
                ->icon(TablerIcon::ExternalLink)
                ->color('gray')
                ->url(fn (PluginHubService $hub) => $hub->hubUrl(), true),
        ];
    }

    public function table(Table $table): Table
    {
        $hub = $this->hub;

        return $table
            ->records(function (?string $search, array $filters, int $page, int $recordsPerPage) use ($hub) {
                $results = $hub->search(
                    $search,
                    $filters['sort']['value'] ?? 'popular',
                    $filters['category']['value'] ?? null,
                    $filters['tags']['values'] ?? [],
                    $page,
                    $recordsPerPage,
                );

                $this->hubUnreachable = $results === null;

                if ($results === null) {
                    return new LengthAwarePaginator([], 0, $recordsPerPage, $page);
                }

                // Key records by slug so actions resolve the right plugin.
                return new LengthAwarePaginator(
                    $results->getCollection()->map(fn (array $plugin) => [...$plugin, '__key' => $plugin['slug']]),
                    $results->total(),
                    $results->perPage(),
                    $results->currentPage(),
                );
            })
            ->searchable()
            ->searchDebounce('750ms')
            ->paginated([12, 24, 48])
            ->defaultPaginationPageOption(24)
            ->columns([
                TextColumn::make('title')
                    ->label(trans('admin/plugin.name'))
                    ->weight('bold')
                    ->description(fn (array $record) => trans('admin/plugin.hub.by_author', ['author' => $record['author_name'] ?? '?'])),
                TextColumn::make('description')
                    ->label(trans('admin/plugin.hub.description'))
                    // Hub descriptions are markdown; show them as plain text in the table.
                    ->state(fn (array $record) => $this->plainText($record['description'] ?? null))
                    ->limit(100)
                    ->wrap(),
                TextColumn::make('categories')
                    ->label(trans('admin/plugin.category'))
                    ->state(fn (array $record) => collect($record['categories'] ?? [])->pluck('name')->filter()->all())
                    ->badge()
                    ->visibleFrom('lg'),
                TextColumn::make('latest_version')
                    ->label(trans('admin/plugin.version'))
                    ->badge()
                    ->color(fn (array $record) => $this->installState($record) === 'update' ? 'warning' : 'gray')
                    ->description(fn (array $record) => match ($this->installState($record)) {
                        'installed' => trans('admin/plugin.hub.installed'),
                        'update' => trans('admin/plugin.hub.installed_version', ['version' => Plugin::find($record['slug'])?->version]),
                        default => null,
                    }),
                TextColumn::make('downloads_count')
                    ->label(trans('admin/plugin.hub.downloads'))
                    ->icon(TablerIcon::Download)
                    ->numeric()
                    ->visibleFrom('md'),
                TextColumn::make('review_percentage')
                    ->label(trans('admin/plugin.hub.rating'))
                    ->icon(TablerIcon::Star)
                    ->formatStateUsing(fn ($state) => is_numeric($state) ? round((float) $state) . '%' : null)
                    ->placeholder('-')
                    ->visibleFrom('md'),
                TextColumn::make('last_version_at')
                    ->label(trans('admin/plugin.hub.updated'))
                    ->formatStateUsing(fn ($state) => filled($state) ? Carbon::parse($state)->diffForHumans() : null)
                    ->placeholder('-')
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('sort')
                    ->label(trans('admin/plugin.hub.sort'))
                    ->options(collect(PluginHubService::SORTS)->mapWithKeys(fn ($sort) => [$sort => trans("admin/plugin.hub.sort_options.$sort")])->all())
                    ->default('popular')
                    ->selectablePlaceholder(false),
                SelectFilter::make('category')
                    ->label(trans('admin/plugin.category'))
                    ->options(fn () => $hub->categories()),
                SelectFilter::make('tags')
                    ->label(trans('admin/plugin.hub.tags'))
                    ->multiple()
                    ->options(fn () => $hub->tags()),
            ])
            ->recordActions([
                Action::make('exclude_view')
                    ->label(trans('filament-actions::view.single.label'))
                    ->icon(TablerIcon::Eye)
                    ->color('gray')
                    ->slideOver()
                    ->modalHeading(fn (array $record) => $record['title'] ?? $record['slug'])
                    ->modalDescription(fn (array $record) => trans('admin/plugin.hub.by_author', ['author' => $record['author_name'] ?? '?']))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(trans('filament::components/modal.actions.close.label'))
                    ->extraModalFooterActions(fn (array $record) => $this->linkActions($hub->find($record['slug']) ?? $record))
                    ->schema(fn (array $record) => $this->detailSchema($hub->find($record['slug']) ?? $record)),
                Action::make('exclude_install')
                    ->label(fn (array $record) => match ($this->installState($record)) {
                        'update' => trans('admin/plugin.update'),
                        'installed' => trans('admin/plugin.hub.reinstall'),
                        default => trans('admin/plugin.install'),
                    })
                    ->icon(TablerIcon::Download)
                    ->color(fn (array $record) => $this->installState($record) === 'installed' ? 'gray' : 'success')
                    ->authorize(fn () => user()?->can('create', Plugin::class))
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record) => trans('admin/plugin.hub.install_heading', ['name' => $record['title'] ?? $record['slug']]))
                    ->modalDescription(trans('admin/plugin.hub.install_description'))
                    ->action(fn (array $record, PluginService $pluginService) => $this->installFromHub($record['slug'], $hub, $pluginService)),
                ReportPluginIssueAction::make(),
            ])
            ->emptyStateIcon(fn () => $this->hubUnreachable ? TablerIcon::CloudOff : TablerIcon::Packages)
            ->emptyStateHeading(fn () => $this->hubUnreachable ? trans('admin/plugin.hub.unreachable') : trans('admin/plugin.hub.no_results'))
            ->emptyStateDescription(fn () => $this->hubUnreachable ? trans('admin/plugin.hub.unreachable_description', ['url' => $hub->hubUrl()]) : '');
    }

    public function installFromHub(string $slug, PluginHubService $hub, PluginService $pluginService): void
    {
        try {
            $plugin = $hub->find($slug);

            // Re-check on the server: only free plugins may ever be installed from here.
            if ($plugin === null || !$hub->isFree($plugin)) {
                throw new RuntimeException(trans('admin/plugin.hub.not_free'));
            }

            $release = $hub->latestRelease($slug);

            if ($release === null) {
                throw new RuntimeException(trans('admin/plugin.hub.no_release'));
            }

            Plugin::refreshRows();
            $wasInstalled = Plugin::whereKey($slug)->exists() && $pluginService->isInstalled($slug);

            $id = $pluginService->downloadPluginFromUrl($release['download_url'], $slug, $release['sha256']);

            // Pick up the new plugin so the table shows it as installed.
            Plugin::refreshRows();

            InstallPlugin::dispatch(user(), $id, reinstall: $wasInstalled);

            Notification::make()
                ->success()
                ->title(trans('admin/plugin.notifications.install_started'))
                ->body(trans('admin/plugin.notifications.background_info'))
                ->send();
        } catch (Exception $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(trans('admin/plugin.notifications.install_error'))
                ->body($exception->getMessage())
                ->send();
        }
    }

    private function plainText(mixed $markdown): ?string
    {
        if (!is_string($markdown) || blank($markdown)) {
            return null;
        }

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(Str::markdown($markdown, ['html_input' => 'strip'])))));
    }

    /**
     * Whether a Hub plugin is installed here, and if so whether the Hub has a newer version.
     *
     * @param  array<string, mixed>  $record
     * @return 'not_installed'|'installed'|'update'
     */
    private function installState(array $record): string
    {
        $plugin = Plugin::find($record['slug'] ?? null);

        if ($plugin === null) {
            return 'not_installed';
        }

        $latest = $record['latest_version'] ?? null;

        return filled($latest) && version_compare((string) $latest, $plugin->version, '>') ? 'update' : 'installed';
    }

    /**
     * @param  array<string, mixed>  $plugin
     * @return array<int, mixed>
     */
    private function detailSchema(array $plugin): array
    {
        $screenshots = collect($plugin['screenshots'] ?? [])->filter(fn ($url) => is_string($url) && str_starts_with($url, 'https://'))->values()->all();

        return [
            TextEntry::make('description')
                ->hiddenLabel()
                ->markdown()
                ->state($plugin['description'] ?? null),
            ImageEntry::make('screenshots')
                ->hiddenLabel()
                ->state($screenshots)
                ->imageHeight(180)
                ->visible($screenshots !== []),
            TextEntry::make('latest_version')
                ->label(trans('admin/plugin.version'))
                ->state($plugin['latest_version'] ?? null)
                ->placeholder('-'),
            TextEntry::make('license')
                ->label(trans('admin/plugin.hub.license'))
                ->state($plugin['license'] ?? null)
                ->placeholder('-'),
            TextEntry::make('categories')
                ->label(trans('admin/plugin.category'))
                ->state($categories = collect($plugin['categories'] ?? [])->pluck('name')->filter()->all())
                ->badge()
                ->visible($categories !== []),
            TextEntry::make('tags')
                ->label(trans('admin/plugin.hub.tags'))
                ->state($tags = collect($plugin['tags'] ?? [])->pluck('name')->filter()->all())
                ->badge()
                ->color('gray')
                ->visible($tags !== []),
            TextEntry::make('downloads_count')
                ->label(trans('admin/plugin.hub.downloads'))
                ->state($plugin['downloads_count'] ?? 0)
                ->numeric(),
        ];
    }

    /**
     * @param  array<string, mixed>  $plugin
     * @return Action[]
     */
    private function linkActions(array $plugin): array
    {
        $links = [
            'url' => [trans('admin/plugin.hub.view_on_hub'), TablerIcon::BuildingStore],
            'repository_url' => [trans('admin/plugin.hub.repository'), TablerIcon::BrandGithub],
            'documentation_url' => [trans('admin/plugin.hub.documentation'), TablerIcon::Book],
            'homepage_url' => [trans('admin/plugin.hub.homepage'), TablerIcon::World],
            'discord_support_url' => [trans('admin/plugin.hub.discord_support'), TablerIcon::BrandDiscord],
        ];

        $actions = [];

        foreach ($links as $key => [$label, $icon]) {
            $url = $plugin[$key] ?? null;

            if (is_string($url) && str_starts_with($url, 'https://')) {
                $actions[] = Action::make("exclude_link_$key")
                    ->label($label)
                    ->icon($icon)
                    ->color('gray')
                    ->url($url, true);
            }
        }

        return $actions;
    }
}
