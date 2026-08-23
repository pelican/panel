<?php

namespace App\Extensions\Features\Schemas;

use App\Enums\SubuserPermission;
use App\Extensions\Features\FeatureSchemaInterface;
use App\Facades\Activity;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;

class JavaVersionSchema implements FeatureSchemaInterface
{
    /** @return array<string> */
    public function getListeners(): array
    {
        return [
            'java.lang.UnsupportedClassVersionError',
            'unsupported major.minor version',
            'has been compiled by a more recent version of the java runtime',
            'minecraft 1.17 requires running the server with java 16 or above',
            'minecraft 1.18 requires running the server with java 17 or above',
            'minecraft 1.19 requires running the server with java 17 or above',
        ];
    }

    public function getId(): string
    {
        return 'java_version';
    }

    public function authorize(User $user, Server $server): bool
    {
        return $user->can(SubuserPermission::StartupDockerImage, $server);
    }

    public function getAction(): Action
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalHeading(trans('server/feature.java_version.heading'))
            ->modalDescription(trans('server/feature.java_version.description'))
            ->modalSubmitActionLabel(trans('server/feature.java_version.submit'))
            ->schema([
                TextEntry::make('java')
                    ->label(trans('server/feature.java_version.select_version')),
                Select::make('image')
                    ->label(trans('server/feature.java_version.docker_image'))
                    ->disabled(fn () => !in_array($server->image, $server->egg->docker_images))
                    ->options(fn () => collect($server->egg->docker_images)->mapWithKeys(fn ($key, $value) => [$key => $value]))
                    ->selectablePlaceholder(false)
                    ->default(fn () => $server->image)
                    ->notIn(fn () => $server->image)
                    ->required()
                    ->preload(),
            ])
            ->action(function (array $data, DaemonServerRepository $serverRepository) use ($server) {
                try {
                    $new = $data['image'];
                    $original = $server->image;
                    $server->forceFill(['image' => $new])->saveOrFail();

                    if ($original !== $server->image) {
                        Activity::event('server:startup.image')
                            ->property(['old' => $original, 'new' => $new])
                            ->log();
                    }

                    $serverRepository->setServer($server)->power('restart');

                    Notification::make()
                        ->title(trans('server/feature.java_version.updated'))
                        ->body(trans('server/feature.restart_now'))
                        ->success()
                        ->send();
                } catch (Exception $exception) {
                    Notification::make()
                        ->title(trans('server/feature.java_version.failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
