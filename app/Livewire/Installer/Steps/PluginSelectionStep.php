<?php

namespace App\Livewire\Installer\Steps;

use App\Enums\PluginCategory;
use App\Enums\TablerIcon;
use App\Livewire\Installer\PanelInstaller;
use App\Services\Helpers\PluginHubService;
use Filament\Forms\Components\CheckboxList;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Str;

class PluginSelectionStep
{
    /** The most the Hub returns on one page. Anything further down can be installed from the panel later. */
    private const MAX_PLUGINS = 100;

    public static function make(PanelInstaller $installer): Step
    {
        return Step::make('plugin')
            ->label(trans('installer.plugin.title'))
            ->columnSpanFull()
            ->schema(self::schema($installer->hubPlugins));
    }

    /**
     * The Hub's most popular free plugins, cut down to what the step shows since
     * this is kept on the page between requests. Empty when the Hub can't be reached.
     *
     * @return array<int, array{slug: string, title: string, type: string, description: string}>
     */
    public static function loadPlugins(PluginHubService $hub): array
    {
        $plugins = $hub->search(sort: 'popular', perPage: self::MAX_PLUGINS)?->getCollection() ?? collect();

        return $plugins
            ->filter(fn (array $plugin) => PluginCategory::tryFrom((string) ($plugin['type'] ?? '')) !== null)
            ->map(fn (array $plugin) => [
                'slug' => (string) $plugin['slug'],
                'title' => (string) ($plugin['title'] ?? $plugin['slug']),
                'type' => (string) $plugin['type'],
                'description' => self::describe($plugin, $hub),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{slug: string, title: string, type: string, description: string}>|null  $plugins  null while still loading
     * @return array<int, mixed>
     */
    private static function schema(?array $plugins): array
    {
        if ($plugins === null) {
            return [
                View::make('filament.components.installer-loading')
                    ->viewData(['message' => trans('installer.plugin.loading')]),
            ];
        }

        // The Hub being unavailable must never block installing the panel.
        if ($plugins === []) {
            return [
                TextEntry::make('no_plugins')
                    ->hiddenLabel()
                    ->state(trans('installer.plugin.unavailable')),
            ];
        }

        $tabs = [];

        foreach (PluginCategory::cases() as $category) {
            $listed = collect($plugins)->where('type', $category->value);

            if ($listed->isEmpty()) {
                continue;
            }

            $tabs[] = Tab::make($category->value)
                ->label($category->getLabel())
                ->icon($category->getIcon())
                ->badge($listed->count())
                ->schema([
                    CheckboxList::make("plugins.$category->value")
                        ->hiddenLabel()
                        ->options($listed->pluck('title', 'slug')->all())
                        ->descriptions($listed->pluck('description', 'slug')->all())
                        ->searchable()
                        ->columns(3),
                ]);
        }

        return [
            TextEntry::make('plugin_description')
                ->hiddenLabel()
                ->icon(TablerIcon::InfoCircle)
                ->state(trans('installer.plugin.description')),
            Tabs::make('plugin_tabs')
                ->tabs($tabs),
        ];
    }

    /** @param  array<string, mixed>  $plugin */
    private static function describe(array $plugin, PluginHubService $hub): string
    {
        $author = trans('admin/plugin.hub.by_author', ['author' => $plugin['author_name'] ?? '?']);
        $description = $hub->plainDescription($plugin['description'] ?? null);

        return filled($description) ? $author . ' - ' . Str::limit($description, 90) : $author;
    }
}
