<?php

namespace App\Filament\Admin\Resources\ApiKeys\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\ApiKeys\ApiKeyResource;
use App\Models\ApiKey;
use App\Services\Helpers\HubCredentials;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Components\Attributes\Exposed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class CreateApiKey extends CreateRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = ApiKeyResource::class;

    protected static bool $canCreateAnother = false;

    /** Where the new key is POSTed after Create: only ever the configured Pelican Hub. */
    #[Locked]
    public ?string $returnUrl = null;

    #[Locked]
    public ?string $returnState = null;

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
        if ($this->returnUrl === null) {
            return null;
        }

        return trans('admin/apikey.return_to', ['host' => parse_url($this->returnUrl, PHP_URL_HOST)]);
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
            $state['memo'] = Str::substr($memo, 0, 255);
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

        $returnUrl = request()->query('return_url');
        if (is_string($returnUrl) && HubCredentials::isHub($returnUrl)) {
            $this->returnUrl = $returnUrl;

            $returnState = request()->query('state');
            if (is_string($returnState)) {
                $this->returnState = Str::substr($returnState, 0, 255);
            }
        }
    }

    /**
     * Creates the key as usual, then, when the page was opened with a Hub return URL, POSTs the
     * new key to it from the server. That runs after the transaction commits, so the Hub can use
     * the key straight away, and the key never passes through the browser.
     */
    #[Exposed]
    public function create(bool $another = false): void
    {
        if ($this->isCreating) {
            return;
        }

        parent::create($another);

        if ($this->returnUrl === null || !$this->record instanceof ApiKey) {
            return;
        }

        try {
            $sent = Http::timeout(10)
                ->withoutRedirecting()
                ->post($this->returnUrl, [
                    'key' => $this->record->identifier . $this->record->token,
                    'state' => $this->returnState,
                ])
                ->successful();
        } catch (ConnectionException) {
            $sent = false;
        }

        if ($sent) {
            return;
        }

        Notification::make()
            ->title(trans('admin/apikey.return_failed', ['host' => parse_url($this->returnUrl, PHP_URL_HOST)]))
            ->warning()
            ->send();
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
