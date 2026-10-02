<?php

namespace App\Filament\Server\Resources\Subusers;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Server\Resources\Subusers\Pages\ListSubusers;
use App\Models\Server;
use App\Models\Subuser;
use App\Services\Subusers\SubuserCreationService;
use App\Services\Subusers\SubuserDeletionService;
use App\Services\Subusers\SubuserUpdateService;
use App\Traits\Filament\BlockAccessInConflict;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyTable;
use App\Traits\Filament\HasLimitBadge;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubuserResource extends Resource
{
    use BlockAccessInConflict;
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyTable;
    use HasLimitBadge;

    protected static ?string $model = Subuser::class;

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Users;

    protected static function getBadgeCount(): int
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server->subusers->count();
    }

    public static function defaultTable(Table $table): Table
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $tabs = [];
        $permissionsArray = [];

        foreach (Subuser::allPermissionData() as $data) {
            if ($data['hidden']) {
                continue;
            }

            $options = [];
            $descriptions = [];

            foreach ($data['permissions'] as $permission) {
                $options[$permission] = str($permission)->headline();
                $descriptions[$permission] = trans($data['translation_prefix']. '.' . $data['name'] . '_' . str($permission)->replace('-', '_'));
                $permissionsArray[$data['name']][] = $permission;
            }

            $tabs[] = Tab::make($data['name'])
                ->label(trans($data['translation_prefix']. '.' . $data['name'] . '_title'))
                ->schema([
                    Section::make()
                        ->description(trans($data['translation_prefix']. '.' . $data['name'] . '_desc'))
                        ->icon($data['icon'])
                        ->contained(false)
                        ->schema([
                            CheckboxList::make($data['name'])
                                ->hiddenLabel()
                                ->bulkToggleable()
                                ->columns(2)
                                ->options($options)
                                ->descriptions($descriptions),
                        ]),
                ]);
        }

        return $table
            ->paginated(false)
            ->columns([
                ImageColumn::make('picture')
                    ->visibleFrom('lg')
                    ->label('')
                    ->alignCenter()->circular()
                    ->defaultImageUrl(fn (Subuser $subuser) => Filament::getUserAvatarUrl($subuser->user)),
                TextColumn::make('user.username')
                    ->label(trans('server/user.username'))
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label(trans('server/user.email'))
                    ->searchable(),
                TextColumn::make('permissions_count')
                    ->label(trans('server/user.permissions.title'))
                    ->state(fn (Subuser $subuser) => collect($subuser->permissions)
                        ->reject(fn (string $permission) => SubuserPermission::tryFrom($permission)?->isHidden() ?? false)
                        ->count()
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(trans('server/user.edit'))
                    ->hidden(fn (Subuser $subuser) => user()?->id === $subuser->user->id)
                    ->authorize(fn () => user()?->can(SubuserPermission::UserUpdate, $server))
                    ->modalHeading(fn (Subuser $subuser) => trans('server/user.editing', ['user' => $subuser->user->email]))
                    ->successNotificationTitle(null)
                    ->action(function (array $data, SubuserUpdateService $subuserUpdateService, Subuser $subuser) use ($server) {
                        $permissions = collect($data)
                            ->forget('email')
                            ->flatMap(fn ($permissions, $key) => collect($permissions)->map(fn ($permission) => "$key.$permission"))
                            ->push(SubuserPermission::WebsocketConnect->value)
                            ->unique()
                            ->all();

                        $subuserUpdateService->handle($subuser, $server, $permissions);

                        Notification::make()
                            ->title(trans('server/user.notification_edit'))
                            ->success()
                            ->send();

                        return redirect(self::getUrl(tenant: $server));
                    })
                    ->schema([
                        Grid::make()
                            ->columnSpanFull()
                            ->columns([
                                'default' => 1,
                                'sm' => 1,
                                'md' => 5,
                                'lg' => 6,
                            ])
                            ->schema([
                                TextInput::make('email')
                                    ->inlineLabel()
                                    ->disabled()
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 1,
                                        'md' => 4,
                                        'lg' => 5,
                                    ])
                                    ->formatStateUsing(fn (Subuser $subuser) => $subuser->user->email),
                                Actions::make([
                                    Action::make('exclude_assignAll')
                                        ->label(trans('server/user.assign_all'))
                                        ->action(function (Set $set) use ($permissionsArray) {
                                            $permissions = $permissionsArray;
                                            foreach ($permissions as $key => $value) {
                                                $allValues = array_unique($value);
                                                $set($key, $allValues);
                                            }
                                        }),
                                ])
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 1,
                                        'md' => 1,
                                        'lg' => 1,
                                    ]),
                                Tabs::make()
                                    ->columnSpanFull()
                                    ->schema($tabs),
                            ]),
                    ])
                    ->mutateRecordDataUsing(function ($data, Subuser $subuser) {
                        $transformedPermissions = [];

                        foreach ($subuser->permissions as $permission) {
                            [$group, $action] = explode('.', $permission, 2);
                            $transformedPermissions[$group][] = $action;
                        }

                        foreach ($transformedPermissions as $key => $value) {
                            $data[$key] = $value;
                        }

                        return $data;
                    }),
                DeleteAction::make()
                    ->label(trans('server/user.delete'))
                    ->hidden(fn (Subuser $subuser) => user()?->id === $subuser->user->id)
                    ->authorize(fn () => user()?->can(SubuserPermission::UserDelete, $server))
                    ->successNotificationTitle(null)
                    ->action(function (Subuser $subuser, SubuserDeletionService $subuserDeletionService) use ($server) {
                        $subuserDeletionService->handle($subuser, $server);

                        Notification::make()
                            ->title(trans('server/user.notification_delete'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                CreateAction::make('invite')
                    ->hiddenLabel()
                    ->icon(TablerIcon::UserPlus)
                    ->tooltip(trans('server/user.invite_user'))
                    ->createAnother(false)
                    ->authorize(fn () => user()?->can(SubuserPermission::UserCreate, $server))
                    ->schema([
                        Grid::make()
                            ->columnSpanFull()
                            ->columns([
                                'default' => 1,
                                'sm' => 1,
                                'md' => 5,
                                'lg' => 6,
                            ])
                            ->schema([
                                TextInput::make('email')
                                    ->email()
                                    ->inlineLabel()
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 1,
                                        'md' => 4,
                                        'lg' => 5,
                                    ])
                                    ->required(),
                                Actions::make([
                                    Action::make('exclude_assignAll')
                                        ->label(trans('server/user.assign_all'))
                                        ->action(function (Set $set, Get $get) use ($permissionsArray) {
                                            $permissions = $permissionsArray;
                                            foreach ($permissions as $key => $value) {
                                                $allValues = array_unique($value);
                                                $set($key, $allValues);
                                            }
                                        }),
                                ])
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 1,
                                        'md' => 1,
                                        'lg' => 1,
                                    ]),
                                Tabs::make()
                                    ->columnSpanFull()
                                    ->schema($tabs),
                            ]),
                    ])
                    ->modalHeading(trans('server/user.invite_user'))
                    ->modalIcon(TablerIcon::UserPlus)
                    ->modalSubmitActionLabel(trans('server/user.action'))
                    ->successNotificationTitle(null)
                    ->failureNotificationTitle(null)
                    ->action(function (Action $action, array $data, SubuserCreationService $service) use ($server) {
                        $email = strtolower($data['email']);

                        $permissions = collect($data)
                            ->forget('email')
                            ->flatMap(fn ($permissions, $key) => collect($permissions)->map(fn ($permission) => "$key.$permission"))
                            ->push(SubuserPermission::WebsocketConnect->value)
                            ->unique()
                            ->all();

                        try {
                            $subuser = $service->handle($server, $email, $permissions);

                            Activity::event('server:subuser.create')
                                ->subject($subuser->user)
                                ->property([
                                    'email' => $data['email'],
                                    'permissions' => $permissions,
                                ])
                                ->log();

                            Notification::make()
                                ->title(trans('server/user.notification_add'))
                                ->success()
                                ->send();
                        } catch (Exception $exception) {
                            Notification::make()
                                ->title(trans('server/user.notification_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            $action->failure();

                            return;
                        }

                        $action->success();

                        return redirect(self::getUrl(tenant: $server));
                    }), ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListSubusers::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return trans('server/user.title');
    }
}
