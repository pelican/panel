<?php

namespace App\Filament\Admin\Resources\Plugins\Pages;

use App\Enums\PluginCategory;
use App\Filament\Admin\Resources\Plugins\PluginResource;
use App\Models\Plugin;
use App\Services\Helpers\PluginService;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless($this->getTable()->isReorderable(), 403);

        /** @var PluginService $pluginService */
        $pluginService = app(PluginService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $pluginService->updateLoadOrder($order);
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

        return $tabs;
    }
}
