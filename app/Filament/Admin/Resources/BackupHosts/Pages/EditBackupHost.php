<?php

namespace App\Filament\Admin\Resources\BackupHosts\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Pages\BaseAdminEditRecord;
use App\Filament\Admin\Resources\BackupHosts\BackupHostResource;
use App\Filament\Components\Actions\LoggedDeleteAction;
use App\Models\BackupHost;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

class EditBackupHost extends BaseAdminEditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = BackupHostResource::class;

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            LoggedDeleteAction::make()
                ->label(fn (BackupHost $backupHost) => $backupHost->backups()->count() > 0 ? trans('admin/backuphost.delete_help') : trans('filament-actions::delete.single.modal.actions.delete.label'))
                ->disabled(fn (BackupHost $backupHost) => $backupHost->backups()->count() > 0)
                ->hidden(fn () => BackupHost::count() === 1),
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
