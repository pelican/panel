<?php

namespace App\Filament\Admin\Resources\Users\Actions;

use App\Enums\TablerIcon;
use App\Models\User;
use App\Services\Users\AccountSuspensionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

final class UserSuspensionActions
{
    public static function suspend(): Action
    {
        return Action::make('suspendAccount')
            ->label('Suspend account')
            ->icon(TablerIcon::UserOff)
            ->color('danger')
            ->visible(fn (User $record) => !$record->isSuspended() && user()?->can('suspend', $record))
            ->modalHeading(fn (User $record) => "Suspend {$record->username}")
            ->modalDescription('The user will be signed out immediately and will not be able to sign in until the suspension is lifted.')
            ->schema([
                Textarea::make('reason')
                    ->label('Internal reason')
                    ->helperText('This is recorded for administrators and is not shown to the user.')
                    ->required()
                    ->maxLength(5000)
                    ->rows(4),
                Toggle::make('suspend_servers')
                    ->label('Suspend owned servers')
                    ->helperText('Administratively suspends eligible servers owned by this user. Existing server suspensions are preserved.')
                    ->default(false),
            ])
            ->action(function (User $record, array $data, AccountSuspensionService $service): void {
                /** @var User $actor */
                $actor = user();
                $service->suspend($actor, $record, $data['reason'], (bool) ($data['suspend_servers'] ?? false));

                Notification::make()
                    ->title('Account suspended')
                    ->body(($data['suspend_servers'] ?? false) ? 'Eligible owned servers are being suspended.' : 'Owned servers will continue running.')
                    ->success()
                    ->send();
            });
    }

    public static function unsuspend(): Action
    {
        return Action::make('unsuspendAccount')
            ->label('Lift suspension')
            ->icon(TablerIcon::UserShield)
            ->color('success')
            ->visible(fn (User $record) => $record->isSuspended() && user()?->can('suspend', $record))
            ->modalHeading(fn (User $record) => "Lift suspension for {$record->username}")
            ->schema([
                Toggle::make('unsuspend_servers')
                    ->label('Unsuspend servers changed by this action')
                    ->helperText('Only servers recorded as suspended by this account suspension are affected. Servers are not started automatically.')
                    ->default(false),
            ])
            ->requiresConfirmation()
            ->action(function (User $record, array $data, AccountSuspensionService $service): void {
                /** @var User $actor */
                $actor = user();
                $service->unsuspend($actor, $record, (bool) ($data['unsuspend_servers'] ?? false));

                Notification::make()
                    ->title('Account suspension lifted')
                    ->success()
                    ->send();
            });
    }
}
