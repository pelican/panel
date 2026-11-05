<?php

namespace App\Filament\Admin\Resources\BackupHosts\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\BackupHosts\BackupHostResource;
use App\Models\BackupHost;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBackupHost extends EditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = BackupHostResource::class;

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        $hasBackups = fn (BackupHost $backupHost) => once(fn () => $backupHost->backups()->exists());

        return [
            DeleteAction::make()
                ->label(fn (BackupHost $backupHost) => $hasBackups($backupHost) ? trans('admin/backuphost.delete_help') : trans('filament-actions::delete.single.modal.actions.delete.label'))
                ->disabled($hasBackups)

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
