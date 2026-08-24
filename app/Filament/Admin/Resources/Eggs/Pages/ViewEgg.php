<?php

namespace App\Filament\Admin\Resources\Eggs\Pages;

use App\Filament\Admin\Resources\Eggs\EggResource;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\CanCustomizeTabs;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewEgg extends ViewRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use CanCustomizeTabs;

    protected static string $resource = EggResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->tabs($this->getTabs())
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    /** @return Tab[] */
    protected function getDefaultTabs(): array
    {
        return EggResource::detailTabs();
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
