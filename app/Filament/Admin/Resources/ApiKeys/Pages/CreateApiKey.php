<?php

namespace App\Filament\Admin\Resources\ApiKeys\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\ApiKeys\ApiKeyResource;
use App\Models\ApiKey;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateApiKey extends CreateRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = ApiKeyResource::class;

    protected static bool $canCreateAnother = false;

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            Action::make('create')
                ->hiddenLabel()
                ->action('create')
                ->keyBindings(['mod+s'])
                ->tooltip(trans('filament-panels::resources/pages/create-record.form.actions.create.label'))
                ->icon(TablerIcon::FilePlus),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Prefills the form from the query string, so an integration can link straight to a key with
     * the permissions it needs, e.g. `?memo=My+App&permissions[server]=1&permissions[plugin]=3`.
     * Nothing is created until the admin reviews the form and presses Create.
     */
    protected function afterFill(): void
    {
        $state = [];

        $memo = request()->query('memo');
        if (is_string($memo)) {
            $state['memo'] = str($memo)->limit(255, '')->toString();
        }

        $permissions = request()->query('permissions');
        if (is_array($permissions)) {
            foreach (ApiKey::getPermissionList() as $resource) {
                $level = $permissions[$resource] ?? null;

                if (in_array($level, ['0', '1', '3'], true)) {
                    $state['permissions_' . $resource] = (int) $level;
                }
            }
        }

        $this->data = [...$this->data ?? [], ...$state];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['identifier'] = ApiKey::generateTokenIdentifier(ApiKey::TYPE_APPLICATION);
        $data['token'] = Str::random(ApiKey::KEY_LENGTH);
        $data['user_id'] = user()?->id;
        $data['key_type'] = ApiKey::TYPE_APPLICATION;

        $permissions = [];

        foreach (ApiKey::getPermissionList() as $permission) {
            if (isset($data['permissions_' . $permission])) {
                $permissions[$permission] = intval($data['permissions_' . $permission]);
                unset($data['permissions_' . $permission]);
            }
        }

        $data['permissions'] = $permissions;

        return parent::handleRecordCreation($data);
    }
}
