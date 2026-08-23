<?php

namespace App\Extensions\Features\Schemas;

use App\Enums\TablerIcon;
use App\Extensions\Features\FeatureSchemaInterface;
use App\Models\Server;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

class PIDLimitSchema implements FeatureSchemaInterface
{
    /** @return array<string> */
    public function getListeners(): array
    {
        return [
            'pthread_create failed',
            'failed to create thread',
            'unable to create thread',
            'unable to create native thread',
            'unable to create new native thread',
            'exception in thread "craft async scheduler management thread"',
        ];
    }

    public function getId(): string
    {
        return 'pid_limit';
    }

    public function authorize(User $user, Server $server): bool
    {
        return true;
    }

    public function getAction(): Action
    {
        return Action::make($this->getId())
            ->requiresConfirmation()
            ->icon(TablerIcon::AlertTriangle)
            ->modalHeading(fn () => user()?->isAdmin() ? trans('server/feature.pid_limit.heading_admin') : trans('server/feature.pid_limit.heading_user'))
            ->modalDescription(new HtmlString(user()?->isAdmin() ? trans('server/feature.pid_limit.description_admin') : trans('server/feature.pid_limit.description_user')))
            ->modalCancelActionLabel(trans('server/feature.close'))
            ->action(fn () => null);
    }
}
