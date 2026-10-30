<?php

namespace App\Filament\Admin\Resources\Plugins\Pages;

use App\Enums\PluginCategory;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Plugins\PluginResource;
use App\Livewire\AlertBanner;
use App\Models\Plugin;
use App\Models\WingsPlugin;
use App\Services\Helpers\PluginService;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;

    private function isWingsTab(): bool
    {
        return $this->activeTab === 'wings';
    }

    /**
     * The Wings tab lists plugins that live on the nodes rather than in this
     * installation, so the table reads a different model for it.
     *
     * Both are Sushi models over data that is not in the Panel's database, the
     * Panel's own from the plugins directory and these from each node's API, so
     * the table itself does not have to know the difference.
     */
    protected function getTableQuery(): Builder|Relation|null
    {
        if ($this->isWingsTab()) {
            return WingsPlugin::query();
        }

        return parent::getTableQuery();
    }

    /**
     * Reordering sets load order, which decides the order plugins load and
     * therefore which one gets to act on something first. Wings keeps its own
     * order per node, so the change is sent to the node the plugins came from.
     *
     * @param  array<int, string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless($this->getTable()->isReorderable(), 403);

        if (!$this->isWingsTab()) {
            /** @var PluginService $pluginService */
            $pluginService = app(PluginService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

            $pluginService->updateLoadOrder($order);

            return;
        }

        // Rows are keyed "<node id>:<plugin id>" because one page lists every
        // node. Group them back by node so each node is told the order of its
        // own plugins and nothing else.
        $byNode = [];
        foreach ($order as $key) {
            [$nodeId, $pluginId] = array_pad(explode(':', $key, 2), 2, null);
            if ($pluginId === null || $pluginId === '') {
                continue;
            }
            $byNode[$nodeId][] = $pluginId;
        }

        foreach ($byNode as $nodeId => $plugins) {
            $plugin = WingsPlugin::query()->where('node_id', $nodeId)->first();
            if (!$plugin instanceof WingsPlugin) {
                continue;
            }

            try {
                $plugin->repository()->setLoadOrder($plugins);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        WingsPlugin::flush();
    }

    public function mount(): void
    {
        parent::mount();

        if (config('panel.plugin.safe_mode')) {
            AlertBanner::make('plugin_safe_mode')
                ->title(trans('admin/plugin.safe_mode_active'))
                ->warning()
                ->send();
        }
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('all')
                ->label(trans('admin/plugin.all'))
                ->badge(Plugin::count()),
        ];

        foreach (PluginCategory::cases() as $category) {
            $query = Plugin::whereCategory($category->value);
            $tabs[$category->value] = Tab::make($category->value)
                ->label($category->getLabel())
                ->icon($category->getIcon())
                ->badge($query->count())
                ->modifyQueryUsing(fn () => $query);
        }

        // Plugins on the nodes rather than in the Panel. The count is left off
        // when the nodes cannot be reached, because a zero there would read as
        // "no plugins installed" when the truth is "we could not ask".
        $tabs['wings'] = Tab::make('wings')
            ->label(trans('admin/plugin.tabs_wings'))
            ->icon(TablerIcon::Server2)
            ->badge(fn () => $this->wingsBadge());

        return $tabs;
    }

    private function wingsBadge(): ?string
    {
        try {
            return (string) WingsPlugin::count() ?: null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
