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
     * Only the nodes this admin can access are asked, and only their rows are listed (contract A7).
     *
     * @return Builder<WingsPlugin>
     */
    private function wingsPlugins(): Builder
    {
        return WingsPlugin::scoped();
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
     * Only the Panel's own plugins have a load order; Wings answers 501 to one (contract A6).
     *
     * @param  array<int, string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless($this->getTable()->isReorderable() && !$this->isWingsTab(), 403);

        /** @var PluginService $pluginService */
        $pluginService = app(PluginService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $pluginService->updateLoadOrder($order);
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

    /**
     * The number of plugins on the admin's nodes. Nodes are only asked on the Wings tab, and a node that
     * didn't answer or a plugin that couldn't be read makes the count unknown. A node that doesn't let the
     * Panel list its plugins is known to have none to show.
     */
    private function wingsBadge(): ?string
    {
        if (!$this->isWingsTab() && !WingsPlugin::isCached()) {
            return null;
        }

        try {
            $rows = $this->wingsPlugins()->get();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if ($rows->contains(fn (WingsPlugin $row) => $row->row_type === WingsPlugin::ROW_ERROR)) {
            return null;
        }

        $count = $rows->filter(fn (WingsPlugin $row) => $row->isPlugin())->count();

        return $count > 0 ? (string) $count : null;
    }
}
