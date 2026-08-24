<?php

namespace App\Filament\Admin\Resources\Servers\Pages;

use App\Filament\Admin\Resources\Servers\ServerResource;
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
use Random\RandomException;

class ViewServer extends ViewRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use CanCustomizeTabs;

    protected static string $resource = ServerResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->persistTabInQueryString()
                    ->columns([
                        'default' => 2,
                        'sm' => 2,
                        'md' => 4,
                        'lg' => 6,
                    ])
                    ->columnSpanFull()
                    ->tabs($this->getTabs()),
            ]);
    }

    /**
     * @return Tab[]
     *
     * @throws RandomException
     */
    protected function getDefaultTabs(): array
    {
        return ServerResource::detailTabs(false);
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
