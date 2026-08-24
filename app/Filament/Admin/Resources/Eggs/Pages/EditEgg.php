<?php

namespace App\Filament\Admin\Resources\Eggs\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Eggs\EggResource;
use App\Filament\Components\Actions\ExportEggAction;
use App\Filament\Components\Actions\ImportEggAction;
use App\Models\Egg;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\CanCustomizeTabs;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class EditEgg extends EditRecord
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
            DeleteAction::make()
                ->disabled(fn (Egg $egg): bool => $egg->servers()->count() > 0)
                ->tooltip(fn (Egg $egg): string => $egg->servers()->count() <= 0 ? trans('filament-actions::delete.single.label') : trans('admin/egg.in_use')),
            ExportEggAction::make(),
            ImportEggAction::make()
                ->multiple(false)
                ->after(function () {
                    $this->record->refresh();
                    $this->refreshForm();
                }),
            Action::make('save')
                ->hiddenLabel()
                ->action('save')
                ->keyBindings(['mod+s'])
                ->tooltip(trans('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->icon(TablerIcon::DeviceFloppy),
        ];
    }

    public function refreshForm(): void
    {
        $this->fillForm();

        $this->dispatch('setContent', content: $this->record->script_install ?? '');
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
