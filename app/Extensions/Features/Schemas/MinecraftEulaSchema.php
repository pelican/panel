<?php

namespace App\Extensions\Features\Schemas;

use App\Enums\SubuserPermission;
use App\Extensions\Features\FeatureSchemaInterface;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class MinecraftEulaSchema implements FeatureSchemaInterface
{
    /** @return array<string> */
    public function getListeners(): array
    {
        return [
            'you need to agree to the eula in order to run the server',
        ];
    }

    public function getId(): string
    {
        return 'eula';
    }

    public function authorize(User $user, Server $server): bool
    {
        return $user->can(SubuserPermission::FileUpdate, $server);
    }

    public function getAction(): Action
    {
        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalHeading(trans('server/feature.eula.heading'))
            ->modalDescription(new HtmlString(Blade::render(trans('server/feature.eula.description'))))
            ->modalSubmitActionLabel(trans('server/feature.eula.accept'))
            ->action(function (DaemonFileRepository $fileRepository, DaemonServerRepository $serverRepository) {
                try {
                    /** @var Server $server */
                    $server = Filament::getTenant();

                    $fileRepository->setServer($server)->putContent('eula.txt', 'eula=true');

                    $serverRepository->setServer($server)->power('restart');

                    Notification::make()
                        ->title(trans('server/feature.eula.accepted'))
                        ->body(trans('server/feature.restart_now'))
                        ->success()
                        ->send();
                } catch (Exception $exception) {
                    Notification::make()
                        ->title(trans('server/feature.eula.failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
