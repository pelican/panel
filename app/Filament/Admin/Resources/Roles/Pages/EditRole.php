<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Admin\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Observers\AuditObserver;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * @property Role $record
 */
class EditRole extends EditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = RoleResource::class;

    public Collection $permissions;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissions = collect($data)
            ->filter(function ($permission, $key) {
                return !in_array($key, ['name', 'guard_name']);
            })
            ->values()
            ->flatten()
            ->unique();

        return Arr::only($data, ['name', 'guard_name']);
    }

    protected function afterSave(): void
    {
        $oldPermissions = $this->record->permissions()->pluck('name')->sort()->values()->all();

        $permissionModels = collect();
        $this->permissions->each(function ($permission) use ($permissionModels) {
            $permissionModels->push(Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $this->data['guard_name'],
            ]));
        });

        $this->record->syncPermissions($permissionModels);

        $newPermissions = $this->record->permissions()->pluck('name')->sort()->values()->all();

        // Permissions are a relation, so the AuditObserver never sees them change.
        if ($oldPermissions !== $newPermissions) {
            Activity::event('role:update')
                ->subject($this->record)
                ->property(AuditObserver::identify($this->record))
                ->property('changes', ['permissions' => ['old' => $oldPermissions, 'new' => $newPermissions]])
                ->log();
        }
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->tooltip(fn (Role $role) => $role->isRootAdmin() ? trans('admin/role.root_admin_delete') : ($role->users_count >= 1 ? trans('admin/role.in_use') : trans('filament-actions::delete.single.label')))
                ->disabled(fn (Role $role) => $role->isRootAdmin() || $role->users_count >= 1),
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
