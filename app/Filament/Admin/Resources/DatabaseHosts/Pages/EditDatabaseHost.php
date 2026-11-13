<?php

namespace App\Filament\Admin\Resources\DatabaseHosts\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\DatabaseHosts\DatabaseHostResource;
use App\Models\DatabaseHost;
use App\Services\Databases\Hosts\HostUpdateService;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use PDOException;

class EditDatabaseHost extends EditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = DatabaseHostResource::class;

    private HostUpdateService $hostUpdateService;

    public function boot(HostUpdateService $hostUpdateService): void
    {
        $this->hostUpdateService = $hostUpdateService;
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        $hasDatabases = fn (DatabaseHost $databaseHost) => once(fn () => $databaseHost->databases()->exists());

        return [
            DeleteAction::make()
                ->tooltip(fn (DatabaseHost $databaseHost) => $hasDatabases($databaseHost) ? trans('admin/databasehost.delete_help') : trans('filament-actions::delete.single.modal.actions.delete.label'))
                ->disabled($hasDatabases),

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (!$record instanceof DatabaseHost) {
            return $record;
        }

        try {
            return $this->hostUpdateService->handle($record, $data);
        } catch (PDOException $exception) {
            Notification::make()
                ->title(trans('admin/databasehost.error'))
                ->body($exception->getMessage())
                ->color('danger')
                ->icon(TablerIcon::Database)
                ->danger()
                ->send();

            throw new Halt();
        }
    }
}
