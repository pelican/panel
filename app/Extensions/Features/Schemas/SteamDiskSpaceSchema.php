<?php

namespace App\Extensions\Features\Schemas;

use App\Extensions\Features\FeatureSchemaInterface;
use App\Models\Server;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

class SteamDiskSpaceSchema implements FeatureSchemaInterface
{
    /** @return array<string> */
    public function getListeners(): array
    {
        return [
            'steamcmd needs 250mb of free disk space to update',
            '0x202 after update job',
        ];
    }

    public function getId(): string
    {
        return 'steam_disk_space';
    }

    public function authorize(User $user, Server $server): bool
    {
        return true;
    }

    public function getAction(): Action
    {
        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalHeading(trans('server/feature.steam_disk_space.heading'))
            ->modalDescription(new HtmlString(user()?->isAdmin() ? trans('server/feature.steam_disk_space.description_admin') : trans('server/feature.steam_disk_space.description_user')))
            ->modalCancelActionLabel(trans('server/feature.close'))
            ->action(fn () => null);
    }
}
