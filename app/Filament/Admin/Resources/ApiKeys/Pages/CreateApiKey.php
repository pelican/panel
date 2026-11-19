<?php

namespace App\Filament\Admin\Resources\ApiKeys\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\ApiKeys\ApiKeyResource;
use App\Models\ApiKey;
use App\Services\Api\HubAuthorizationService;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class CreateApiKey extends CreateRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = ApiKeyResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * Set when the page was opened with a valid Pelican Hub authorization request: after Create,
     * the browser goes back to the Hub with a one-time code (see HubAuthorizationService).
     *
     * @var array{redirect_uri: string, state: string, code_challenge: string}|null
     */
    #[Locked]
    public ?array $hubRequest = null;

    protected ?string $hubCode = null;

    protected HubAuthorizationService $hubAuthorization;

    public function boot(HubAuthorizationService $hubAuthorization): void
    {
        $this->hubAuthorization = $hubAuthorization;
    }

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

    public function getSubheading(): ?string
    {
        if ($this->hubRequest === null) {
            return null;
        }

        return trans('admin/apikey.return_to', ['host' => parse_url($this->hubRequest['redirect_uri'], PHP_URL_HOST)]);
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
            $state['memo'] = mb_substr($memo, 0, 255);
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

        $this->hubRequest = $this->hubAuthorization->authorizationRequest(request()->query());
    }

    protected function afterCreate(): void
    {
        if ($this->hubRequest !== null && $this->record instanceof ApiKey) {
            $this->hubCode = $this->hubAuthorization->issueCode($this->record, $this->hubRequest['code_challenge']);
        }
    }

    protected function getRedirectUrl(): string
    {
        if ($this->hubRequest === null || $this->hubCode === null) {
            return parent::getRedirectUrl();
        }

        return $this->hubRequest['redirect_uri'] . '?' . http_build_query([
            'code' => $this->hubCode,
            'state' => $this->hubRequest['state'],
        ]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        // The admin is on the Hub next; the "Created" toast would only show on their next Panel visit.
        return $this->hubCode === null ? parent::getCreatedNotification() : null;
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
