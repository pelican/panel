<?php

namespace App\Filament\Admin\Resources\Users\Actions;

use App\Enums\TablerIcon;
use App\Models\User;
use App\Services\Users\AccountSuspensionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

final class UserSuspensionActions
{
    public static function suspend(): Action
    {
        return Action::make('suspendAccount')
            ->label(trans('admin/user.suspension.actions.suspend'))
            ->icon(TablerIcon::UserOff)
            ->color('danger')
            ->visible(fn (User $record) => !$record->isSuspended() && user()->isNot($record) && user()->can('update', $record))
            ->modalHeading(fn (User $record) => trans('admin/user.suspension.actions.suspend_heading', ['username' => $record->username]))
            ->modalDescription(trans('admin/user.suspension.actions.suspend_description'))
            ->requiresConfirmation()
            ->action(function (User $record, AccountSuspensionService $service): void {
                /** @var User $actor */
                $actor = user();
                $service->suspend($actor, $record);

                Notification::make()
                    ->title(trans('admin/user.suspension.notifications.suspended'))
                    ->success()
                    ->send();
            });
    }

    public static function unsuspend(): Action
    {
        return Action::make('unsuspendAccount')
            ->label(trans('admin/user.suspension.actions.unsuspend'))
            ->icon(TablerIcon::UserShield)
            ->color('success')
            ->visible(fn (User $record) => $record->isSuspended() && user()->isNot($record) && user()->can('update', $record))
            ->modalHeading(fn (User $record) => trans('admin/user.suspension.actions.unsuspend_heading', ['username' => $record->username]))
            ->modalDescription(trans('admin/user.suspension.actions.unsuspend_description'))
            ->requiresConfirmation()
            ->action(function (User $record, AccountSuspensionService $service): void {
                /** @var User $actor */
                $actor = user();
                $service->unsuspend($actor, $record);

                Notification::make()
                    ->title(trans('admin/user.suspension.notifications.unsuspended'))
                    ->success()
                    ->send();
            });
    }
}
