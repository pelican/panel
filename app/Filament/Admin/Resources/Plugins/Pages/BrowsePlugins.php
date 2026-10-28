<?php

namespace App\Filament\Admin\Resources\Plugins\Pages;

use App\Enums\PluginStatus;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Plugins\PluginResource;
use App\Jobs\Plugin\InstallPlugin;
use App\Models\Plugin;
use App\Services\Helpers\PluginHubService;
use App\Services\Helpers\PluginService;
use Carbon\Carbon;
use Closure;
use Exception;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Browse and install free plugins from the Pelican Hub without leaving the panel.
 */
class BrowsePlugins extends Page implements HasTable
{
    use InteractsWithTable;

    /** Sortable columns and the Hub sort that orders by them. */
    private const COLUMN_SORTS = [
        'downloads_count' => 'popular',
        'review_percentage' => 'rating',
    ];

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
            ->records(function (?string $search, array $filters, int $page, int $recordsPerPage, ?string $sortColumn, ?string $sortDirection) use ($hub) {
                // A sorted column header takes priority over the sort filter.
                $sort = self::COLUMN_SORTS[$sortColumn] ?? $filters['sort']['value'] ?? 'popular';
                $ascending = isset(self::COLUMN_SORTS[$sortColumn]) && $sortDirection === 'asc';

                if ($filters['installed']['isActive'] ?? false) {
                    return $this->installedRecords($hub, $search, $filters, $sort, $ascending, $page, $recordsPerPage);
                }

                $fetch = fn (int $page) => $hub->search(
                    $search,
                    $sort,
                    $filters['category']['value'] ?? null,
                    $filters['tags']['values'] ?? [],
                    $page,
                    $recordsPerPage,
                );

                $results = $ascending ? $this->reversedPage($fetch, $page, $recordsPerPage) : $fetch($page);

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
                TextColumn::make('latest_version')
                    ->label(trans('admin/plugin.version'))
                    ->badge()
                    ->color(fn (array $record) => $this->installState($record) === 'update' ? 'warning' : 'gray')
                    ->tooltip(fn (array $record) => filled($record['last_version_at'] ?? null)
                        ? trans('admin/plugin.hub.updated') . ': ' . Carbon::parse($record['last_version_at'])->diffForHumans()
                        : null)
                    ->description(fn (array $record) => match ($this->installState($record)) {
                        'installed' => trans('admin/plugin.hub.installed'),
                        'update' => trans('admin/plugin.hub.installed_version', ['version' => Plugin::find($record['slug'])?->version]),
                        default => null,
                    }),
                TextColumn::make('downloads_count')
                    ->label(trans('admin/plugin.hub.downloads'))
                    ->icon(TablerIcon::Download)
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('review_percentage')
                    ->label(trans('admin/plugin.hub.rating'))
                    ->icon(TablerIcon::Star)
                    ->sortable()
                    ->formatStateUsing(fn ($state) => is_numeric($state) ? round((float) $state) . '%' : null)
                    ->placeholder('-')
                    ->visibleFrom('md'),
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
                Filter::make('installed')
                    ->label(trans('admin/plugin.hub.installed'))
                    ->toggle(),
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
                    ->authorize(fn (array $record) => $this->canInstall($record['slug']))
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record) => trans('admin/plugin.hub.install_heading', ['name' => $record['title'] ?? $record['slug']]))
                    ->modalDescription(trans('admin/plugin.hub.install_description'))
                    ->action(fn (array $record, PluginService $pluginService) => $this->installFromHub($record['slug'], $hub, $pluginService)),
            ])
            ->emptyStateIcon(fn () => $this->hubUnreachable ? TablerIcon::CloudOff : TablerIcon::Packages)
            ->emptyStateHeading(fn () => $this->hubUnreachable ? trans('admin/plugin.hub.unreachable') : trans('admin/plugin.hub.no_results'))
            ->emptyStateDescription(fn () => $this->hubUnreachable ? trans('admin/plugin.hub.unreachable_description', ['url' => $hub->hubUrl()]) : '');
    }

    public function installFromHub(string $slug, PluginHubService $hub, PluginService $pluginService): void
    {
        try {
            Plugin::refreshRows();

            // Re-check on the server too: replacing a plugin's files needs permission to update it.
            if (!$this->canInstall($slug)) {
                throw new RuntimeException(trans('admin/plugin.hub.no_permission'));
            }

            $plugin = $hub->find($slug);

            // Re-check on the server: only free plugins may ever be installed from here.
            if ($plugin === null || !$hub->isFree($plugin)) {
                throw new RuntimeException(trans('admin/plugin.hub.not_free'));
            }

            $release = $hub->latestRelease($slug);

            if ($release === null) {
                throw new RuntimeException(trans('admin/plugin.hub.no_release'));
            }

            // Plugin code runs inside the panel, so never install a download that can't be verified.
            if (!str_starts_with($release['download_url'], 'https://') || preg_match('/\A[a-f0-9]{64}\z/i', (string) $release['sha256']) !== 1) {
                throw new RuntimeException(trans('admin/plugin.hub.unverified_release'));
            }

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
     * Hub listings for plugins present on disk. The Hub search can't filter by
     * what's installed here, so look each one up and filter/paginate locally.
     *
     * @param  array<string, mixed>  $filters
     */
    private function installedRecords(PluginHubService $hub, ?string $search, array $filters, string $sort, bool $ascending, int $page, int $recordsPerPage): LengthAwarePaginator
    {
        $category = $filters['category']['value'] ?? null;
        $tags = array_filter($filters['tags']['values'] ?? [], 'filled');
        $slugsOf = fn (mixed $items) => collect(is_array($items) ? $items : [])
            ->map(fn ($item) => is_array($item) ? ($item['slug'] ?? null) : $item)
            ->filter()
            ->all();

        $plugins = $hub->findMany(Plugin::all()->pluck('id')->all());

        $this->hubUnreachable = $plugins === null;

        if ($plugins === null) {
            return new LengthAwarePaginator([], 0, $recordsPerPage, $page);
        }

        $plugins = collect($plugins)
            ->filter(fn (array $plugin) => $hub->isFree($plugin))
            ->filter(fn (array $plugin) => blank($search)
                || Str::contains(($plugin['title'] ?? '') . ' ' . ($plugin['description'] ?? '') . ' ' . $plugin['slug'], trim($search), true))
            ->filter(fn (array $plugin) => blank($category) || in_array($category, $slugsOf($plugin['categories'] ?? null), true))
            ->filter(fn (array $plugin) => empty($tags) || array_diff($tags, $slugsOf($plugin['tags'] ?? null)) === []);

        $plugins = match ($sort) {
            'name' => $plugins->sortBy(fn (array $plugin) => mb_strtolower($plugin['title'] ?? $plugin['slug'])),
            'rating' => $plugins->sortByDesc(fn (array $plugin) => (float) ($plugin['review_percentage'] ?? 0)),
            'updated', 'newest' => $plugins->sortByDesc(fn (array $plugin) => (string) ($plugin['last_version_at'] ?? '')),
            default => $plugins->sortByDesc(fn (array $plugin) => (int) ($plugin['downloads_count'] ?? 0)),
        };

        $plugins = ($ascending ? $plugins->reverse() : $plugins)->values()->map(fn (array $plugin) => [...$plugin, '__key' => $plugin['slug']]);

        return new LengthAwarePaginator($plugins->forPage($page, $recordsPerPage)->values(), $plugins->count(), $recordsPerPage, $page);
    }

    /**
     * The Hub only sorts high to low, so build an ascending page from the
     * matching slice at the end of the descending results, reversed.
     *
     * @param  Closure(int): ?LengthAwarePaginator<int, array<string, mixed>>  $search
     * @return LengthAwarePaginator<int, array<string, mixed>>|null
     */
    private function reversedPage(Closure $search, int $page, int $perPage): ?LengthAwarePaginator
    {
        $first = $search(1);

        if ($first === null) {
            return null;
        }

        $end = $first->total() - ($page - 1) * $perPage;
        $start = max(0, $end - $perPage);
        $plugins = collect();

        for ($descPage = intdiv($start, $perPage) + 1; $end > 0 && ($descPage - 1) * $perPage < $end; $descPage++) {
            $results = $descPage === 1 ? $first : $search($descPage);

            if ($results === null) {
                return null;
            }

            $plugins->push(...$results->getCollection()->map(fn (array $plugin, int $index) => [($descPage - 1) * $perPage + $index, $plugin]));
        }

        $plugins = $plugins
            ->filter(fn (array $entry) => $entry[0] >= $start && $entry[0] < $end)
            ->map(fn (array $entry) => $entry[1])
            ->reverse()
            ->values();

        return new LengthAwarePaginator($plugins, $first->total(), $perPage, $page);
    }

    /**
     * Adding a plugin needs create permission; replacing one already on disk
     * also needs permission to update it, as on the installed plugins table.
     */
    private function canInstall(string $slug): bool
    {
        if (!user()?->can('create', Plugin::class)) {
            return false;
        }

        $existing = Plugin::find($slug);

        if ($existing !== null) {
            return user()->can('update', $existing);
        }

        // A folder with an unreadable plugin.json has no Plugin row, but installing still replaces it.
        return !File::isDirectory(plugin_path($slug)) || user()->can('update plugin');
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

        // Downloaded but never installed (or a failed install) still needs installing.
        if ($plugin === null || $plugin->status === PluginStatus::NotInstalled) {
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
            ViewEntry::make('screenshots')
                ->hiddenLabel()
                ->view('filament.components.plugin-screenshots')
                ->state($screenshots)
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
