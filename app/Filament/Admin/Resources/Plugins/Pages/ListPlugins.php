<?php

namespace App\Filament\Admin\Resources\Plugins\Pages;

use App\Enums\PluginCategory;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Plugins\PluginResource;
use App\Livewire\AlertBanner;
use App\Models\Plugin;
use App\Models\WingsPlugin;
use App\Services\Helpers\PluginService;
use Filament\Notifications\Notification;
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
     * The rows are cached for everyone, so each user's nodes are filtered here.
     *
     * @return Builder<WingsPlugin>
     */
    private function wingsPlugins(): Builder
    {
        return WingsPlugin::query()->whereIn('node_id', user()?->accessibleNodes()->pluck('id'));
    }

    // The Wings tab lists the plugins on the nodes
    protected function getTableQuery(): Builder|Relation|null
    {
        if ($this->isWingsTab()) {
            return $this->wingsPlugins();
        }

        return parent::getTableQuery();
    }

    /**
     * Wings keeps a load order per node, so it is sent to each plugin's node.
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

        // Rows are keyed "<node id>:<plugin id>", group them back by node
        $byNode = [];
        foreach ($order as $key) {
            [$nodeId, $pluginId] = array_pad(explode(':', $key, 2), 2, null);
            if ($pluginId === null || $pluginId === '') {
                continue;
            }
            $byNode[$nodeId][] = $pluginId;
        }

        $failed = [];

        foreach ($byNode as $nodeId => $plugins) {
            $plugin = $this->wingsPlugins()->where('node_id', $nodeId)->first();
            if (!$plugin instanceof WingsPlugin) {
                $failed[] = (string) $nodeId;

                continue;
            }

            try {
                $plugin->repository()->setLoadOrder($plugins);
            } catch (Throwable $exception) {
                report($exception);

                $failed[] = $plugin->node_name;
            }
        }

        WingsPlugin::flush();

        if ($failed !== []) {
            Notification::make()
                ->danger()
                ->title(trans('admin/plugin.notifications.reorder_error'))
                ->body(trans('admin/plugin.notifications.reorder_error_nodes', ['nodes' => implode(', ', $failed)]))
                ->send();
        }
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

        $tabs['wings'] = Tab::make('wings')
            ->label(trans('admin/plugin.tabs_wings'))
            ->icon(TablerIcon::Server2)
            ->badge(fn () => $this->wingsBadge());

        return $tabs;
    }

    // Don't ask the nodes just for a badge on the other tabs
    private function wingsBadge(): ?string
    {
        if (!$this->isWingsTab() && !cache()->has(WingsPlugin::CACHE_KEY)) {
            return null;
        }

        try {
            $plugins = $this->wingsPlugins()->get();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        // An unreachable node means the count is incomplete
        if ($plugins->contains(fn (WingsPlugin $plugin) => $plugin->error !== null)) {
            return null;
        }

        return $plugins->isNotEmpty() ? (string) $plugins->count() : null;
    }
}
