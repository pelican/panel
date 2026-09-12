<?php

namespace App\Filament\Admin\Resources\Mounts\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Pages\BaseAdminEditRecord;
use App\Filament\Admin\Resources\Mounts\MountResource;
use App\Filament\Components\Actions\LoggedDeleteAction;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

class EditMount extends BaseAdminEditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = MountResource::class;

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            LoggedDeleteAction::make(),
            Action::make('save')
                ->hiddenLabel()
                ->action('save')
                ->keyBindings(['mod+s'])
                ->tooltip(trans('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->icon(TablerIcon::DeviceFloppy),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
