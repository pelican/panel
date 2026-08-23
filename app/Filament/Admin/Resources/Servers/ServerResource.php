<?php

namespace App\Filament\Admin\Resources\Servers;

use App\Enums\CustomizationKey;
use App\Enums\SuspendAction;
use App\Enums\TablerIcon;
use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Extensions\BackupAdapter\Schemas\WingsBackupSchema;
use App\Filament\Admin\Resources\Servers\Pages\CreateServer;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Admin\Resources\Servers\Pages\ListServers;
use App\Filament\Admin\Resources\Servers\Pages\ViewServer;
use App\Filament\Admin\Resources\Servers\RelationManagers\AllocationsRelationManager;
use App\Filament\Admin\Resources\Servers\RelationManagers\DatabasesRelationManager;
use App\Filament\Components\Actions\DeleteIcon;
use App\Filament\Components\Actions\PreviewStartupAction;
use App\Filament\Components\Actions\UploadIcon;
use App\Filament\Components\Forms\Fields\MonacoEditor;
use App\Filament\Components\Forms\Fields\StartupVariable;
use App\Filament\Components\StateCasts\ServerConditionStateCast;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Mount;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Eggs\EggChangerService;
use App\Services\Servers\RandomWordService;
use App\Services\Servers\ReinstallServerService;
use App\Services\Servers\SuspensionService;
use App\Services\Servers\ToggleInstallService;
use App\Services\Servers\TransferServerService;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\StateCasts\BooleanStateCast;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use LogicException;
use Random\RandomException;

class ServerResource extends Resource
{
    use CanCustomizePages;
    use CanCustomizeRelations;

    protected static ?string $model = Server::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::BrandDocker;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return trans('admin/server.nav_title');
    }

    public static function getModelLabel(): string
    {
        return trans('admin/server.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('admin/server.model_label_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        if (user()?->getCustomization(CustomizationKey::TopNavigation) === 'topbar') {
            return null;
        }

        return trans('admin/dashboard.server');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    /**
     * @throws Exception
     */
    public static function getMountCheckboxList(Get $get): CheckboxList
    {
        $allowedMounts = Mount::all();
        $node = $get('node_id');
        $egg = $get('egg_id');

        if ($node && $egg) {
            $allowedMounts = $allowedMounts->filter(fn (Mount $mount) => ($mount->nodes->isEmpty() || $mount->nodes->contains($node)) &&
                ($mount->eggs->isEmpty() || $mount->eggs->contains($egg))
            );
        }

        return CheckboxList::make('mounts')
            ->hiddenLabel()
            ->relationship('mounts')
            ->live()
            ->options(fn () => $allowedMounts->mapWithKeys(fn ($mount) => [$mount->id => $mount->name]))
            ->descriptions(fn () => $allowedMounts->mapWithKeys(fn ($mount) => [$mount->id => "$mount->source -> $mount->target"]))
            ->helperText(fn () => $allowedMounts->isEmpty() ? trans('admin/server.no_mounts') : null)
            ->bulkToggleable()
            ->columnSpanFull();
    }

    /** @return class-string<RelationManager>[] */
    public static function getDefaultRelations(): array
    {
        return [
            AllocationsRelationManager::class,
            DatabasesRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListServers::route('/'),
            'create' => CreateServer::route('/create'),
            'view' => ViewServer::route('/{record}'),
            'edit' => EditServer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query->whereIn('node_id', user()?->accessibleNodes()->pluck('id'));
    }

    /**
     * @return Tab[]
     *
     * @throws RandomException
     */
    public static function detailTabs(bool $materializeVariables = true): array
    {
        return [
            Tab::make('information')
                ->label(trans('admin/server.tabs.information'))
                ->icon(TablerIcon::InfoCircle)
                ->schema([
                    Grid::make()
                        ->columnStart(1)
                        ->schema([
                            Image::make('', 'icon')
                                ->hidden(fn ($record) => !$record->icon && !$record->egg->icon)
                                ->url(fn ($record) => $record->icon ?: $record->egg->icon)
                                ->tooltip(fn ($record) => $record->icon ? '' : trans('server/setting.server_info.icon.tooltip'))
                                ->imageSize(150)
                                ->columnSpanFull()
                                ->alignJustify(),
                            UploadIcon::make()
                                ->hidden(fn (string $operation) => $operation === 'view'),
                            DeleteIcon::make()
                                ->iconStoragePath(Server::getIconStoragePath())
                                ->hidden(fn (string $operation) => $operation === 'view'),
                        ]),
                    Grid::make()
                        ->columns(3)
                        ->columnStart(2)
                        ->columnSpan([
                            'default' => 2,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 5,
                        ])
                        ->schema([
                            TextInput::make('name')
                                ->prefixIcon(TablerIcon::Server)
                                ->label(trans('admin/server.name'))
                                ->suffixAction(Action::make('hint_random')
                                    ->tooltip(trans('admin/server.random'))
                                    ->icon('tabler-dice-' . random_int(1, 6))
                                    ->action(function (Set $set, Get $get) {
                                        $egg = Egg::find($get('egg_id'));
                                        $prefix = $egg ? str($egg->name)->lower()->kebab() . '-' : '';

                                        $word = (new RandomWordService())->word();

                                        $set('name', $prefix . $word);
                                    })
                                    ->hidden(fn (string $operation) => $operation === 'view'))
                                ->columnSpan([
                                    'default' => 2,
                                    'sm' => 1,
                                    'md' => 2,
                                    'lg' => 3,
                                ])
                                ->required()
                                ->maxLength(255),
                            Select::make('owner_id')
                                ->prefixIcon(TablerIcon::User)
                                ->label(trans('admin/server.owner'))
                                ->columnSpan([
                                    'default' => 2,
                                    'sm' => 1,
                                    'md' => 2,
                                    'lg' => 2,
                                ])
                                ->relationship('user', 'username')
                                ->searchable(['username', 'email'])
                                ->getOptionLabelFromRecordUsing(fn (User $user) => "$user->username ($user->email)")
                                ->preload()
                                ->required(),
                            ToggleButtons::make('condition')
                                ->label(trans('admin/server.server_status'))
                                ->formatStateUsing(fn (Server $server) => $server->condition)
                                ->options(fn ($state) => [$state->value => $state->getLabel()])
                                ->colors(fn ($state) => [$state->value => $state->getColor()])
                                ->icons(fn ($state) => [$state->value => $state->getIcon()])
                                ->stateCast(new ServerConditionStateCast())
                                ->columnSpan([
                                    'default' => 2,
                                    'sm' => 1,
                                    'md' => 1,
                                    'lg' => 1,
                                ])
                                ->hintAction(
                                    Action::make('view_install_log')
                                        ->label(trans('admin/server.view_install_log'))
                                        //->visible(fn (Server $server) => $server->isFailedInstall())
                                        ->modalHeading('')
                                        ->modalSubmitAction(false)
                                        ->modalFooterActionsAlignment(Alignment::Right)
                                        ->modalCancelActionLabel(trans('filament::components/modal.actions.close.label'))
                                        ->schema([
                                            MonacoEditor::make('logs')
                                                ->hiddenLabel()
                                                ->formatStateUsing(function (Server $server, DaemonServerRepository $serverRepository) {
                                                    try {
                                                        $logs = $serverRepository->setServer($server)->getInstallLogs();

                                                        return convert_to_utf8($logs);
                                                    } catch (ConnectionException) {
                                                        Notification::make()
                                                            ->title(trans('admin/server.notifications.error_connecting', ['node' => $server->node->name]))
                                                            ->body(trans('admin/server.notifications.log_failed'))
                                                            ->color('warning')
                                                            ->warning()
                                                            ->send();
                                                    } catch (Exception) {
                                                        return '';
                                                    }

                                                    return '';
                                                }),
                                        ])
                                        ->hidden(fn (string $operation) => $operation === 'view')
                                ),
                        ]),
                    Textarea::make('description')
                        ->label(trans('admin/server.description'))
                        ->columnSpanFull(),
                    TextInput::make('uuid')
                        ->label(trans('admin/server.uuid'))
                        ->copyable()
                        ->columnSpan([
                            'default' => 2,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 3,
                        ])
                        ->readOnly()
                        ->dehydrated(false),
                    TextInput::make('uuid_short')
                        ->label(trans('admin/server.short_uuid'))
                        ->copyable()
                        ->columnSpan([
                            'default' => 2,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 3,
                        ])
                        ->readOnly()
                        ->dehydrated(false),
                    TextInput::make('external_id')
                        ->label(trans('admin/server.external_id'))
                        ->columnSpan([
                            'default' => 2,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 3,
                        ])
                        ->unique()
                        ->maxLength(255),
                    Select::make('node_id')
                        ->label(trans('admin/server.node'))
                        ->relationship('node', 'name', fn (Builder $query) => $query->whereIn('id', user()?->accessibleNodes()->pluck('id')))
                        ->columnSpan([
                            'default' => 2,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 3,
                        ])
                        ->disabled(),
                ]),
            Tab::make('environment_configuration')
                ->label(trans('admin/server.tabs.environment_configuration'))
                ->icon(TablerIcon::BrandDocker)
                ->schema([
                    Fieldset::make(trans('admin/server.resource_limits'))
                        ->columnSpanFull()
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 3,
                        ])
                        ->schema([
                            Grid::make()
                                ->columns(4)
                                ->columnSpanFull()
                                ->schema([
                                    ToggleButtons::make('unlimited_cpu')
                                        ->dehydrated()
                                        ->label(trans('admin/server.cpu'))->inlineLabel()->inline()
                                        ->afterStateUpdated(fn (Set $set) => $set('cpu', 0))
                                        ->formatStateUsing(fn (Get $get) => $get('cpu') == 0)
                                        ->live()
                                        ->stateCast(new BooleanStateCast(false, true))
                                        ->options([
                                            1 => trans('admin/server.unlimited'),
                                            0 => trans('admin/server.limited'),
                                        ])
                                        ->colors([
                                            1 => 'primary',
                                            0 => 'warning',
                                        ])
                                        ->columnSpan(2),

                                    TextInput::make('cpu')
                                        ->dehydratedWhenHidden()
                                        ->hidden(fn (Get $get) => $get('unlimited_cpu'))
                                        ->label(trans('admin/server.cpu_limit'))->inlineLabel()
                                        ->suffix('%')
                                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/server.cpu_helper'))
                                        ->required()
                                        ->columnSpan(2)
                                        ->numeric()
                                        ->minValue(0),
                                ]),
                            Grid::make()
                                ->columns(4)
                                ->columnSpanFull()
                                ->schema([
                                    ToggleButtons::make('unlimited_mem')
                                        ->dehydrated()
                                        ->label(trans('admin/server.memory'))->inlineLabel()->inline()
                                        ->afterStateUpdated(fn (Set $set) => $set('memory', 0))
                                        ->formatStateUsing(fn (Get $get) => $get('memory') == 0)
                                        ->live()
                                        ->stateCast(new BooleanStateCast(false, true))
                                        ->options([
                                            1 => trans('admin/server.unlimited'),
                                            0 => trans('admin/server.limited'),
                                        ])
                                        ->colors([
                                            1 => 'primary',
                                            0 => 'warning',
                                        ])
                                        ->columnSpan(2),

                                    TextInput::make('memory')
                                        ->dehydratedWhenHidden()
                                        ->hidden(fn (Get $get) => $get('unlimited_mem'))
                                        ->label(trans('admin/server.memory_limit'))->inlineLabel()
                                        ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/server.memory_helper'))
                                        ->required()
                                        ->columnSpan(2)
                                        ->numeric()
                                        ->minValue(0),
                                ]),

                            Grid::make()
                                ->columns(4)
                                ->columnSpanFull()
                                ->schema([
                                    ToggleButtons::make('unlimited_disk')
                                        ->dehydrated()
                                        ->label(trans('admin/server.disk'))->inlineLabel()->inline()
                                        ->live()
                                        ->afterStateUpdated(fn (Set $set) => $set('disk', 0))
                                        ->formatStateUsing(fn (Get $get) => $get('disk') == 0)
                                        ->stateCast(new BooleanStateCast(false, true))
                                        ->options([
                                            1 => trans('admin/server.unlimited'),
                                            0 => trans('admin/server.limited'),
                                        ])
                                        ->colors([
                                            1 => 'primary',
                                            0 => 'warning',
                                        ])
                                        ->columnSpan(2),

                                    TextInput::make('disk')
                                        ->dehydratedWhenHidden()
                                        ->hidden(fn (Get $get) => $get('unlimited_disk'))
                                        ->label(trans('admin/server.disk_limit'))->inlineLabel()
                                        ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                        ->required()
                                        ->columnSpan(2)
                                        ->numeric()
                                        ->minValue(0),
                                ]),
                        ]),

                    Fieldset::make(trans('admin/server.advanced_limits'))
                        ->columnSpanFull()
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 3,
                        ])
                        ->schema([
                            Grid::make()
                                ->columns(4)
                                ->columnSpanFull()
                                ->schema([
                                    Grid::make()
                                        ->columns(4)
                                        ->columnSpanFull()
                                        ->schema([
                                            ToggleButtons::make('cpu_pinning')
                                                ->label(trans('admin/server.cpu_pin'))->inlineLabel()->inline()
                                                ->default(0)
                                                ->afterStateUpdated(fn (Set $set) => $set('threads', []))
                                                ->formatStateUsing(fn (Get $get) => filled($get('threads')))
                                                ->live()
                                                ->stateCast(new BooleanStateCast(false, true))
                                                ->options([
                                                    0 => trans('admin/server.disabled'),
                                                    1 => trans('admin/server.enabled'),
                                                ])
                                                ->colors([
                                                    0 => 'success',
                                                    1 => 'warning',
                                                ])
                                                ->columnSpan(2),

                                            TagsInput::make('threads')
                                                ->dehydratedWhenHidden()
                                                ->hidden(fn (Get $get) => !$get('cpu_pinning'))
                                                ->label(trans('admin/server.threads'))->inlineLabel()
                                                ->required(fn (Get $get) => $get('cpu_pinning'))
                                                ->columnSpan(2)
                                                ->separator()
                                                ->splitKeys([','])
                                                ->placeholder(trans('admin/server.pin_help')),
                                        ]),
                                    ToggleButtons::make('swap_support')
                                        ->live()
                                        ->label(trans('admin/server.swap'))->inlineLabel()->inline()
                                        ->columnSpan(2)
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            $value = match ($state) {
                                                'unlimited' => -1,
                                                'disabled' => 0,
                                                'limited' => 128,
                                                default => throw new LogicException('Invalid state')
                                            };

                                            $set('swap', $value);
                                        })
                                        ->formatStateUsing(function (Get $get) {
                                            return match (true) {
                                                $get('swap') > 0 => 'limited',
                                                $get('swap') == 0 => 'disabled',
                                                $get('swap') < 0 => 'unlimited',
                                                default => throw new LogicException('Invalid state')
                                            };
                                        })
                                        ->options([
                                            'unlimited' => trans('admin/server.unlimited'),
                                            'limited' => trans('admin/server.limited'),
                                            'disabled' => trans('admin/server.disabled'),
                                        ])
                                        ->colors([
                                            'unlimited' => 'primary',
                                            'limited' => 'warning',
                                            'disabled' => 'danger',
                                        ]),

                                    TextInput::make('swap')
                                        ->dehydratedWhenHidden()
                                        ->hidden(fn (Get $get) => match ($get('swap_support')) {
                                            'disabled', 'unlimited', true => true,
                                            default => false,
                                        })
                                        ->label(trans('admin/server.swap'))->inlineLabel()
                                        ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                        ->minValue(-1)
                                        ->columnSpan(2)
                                        ->required()
                                        ->integer(),
                                ]),

                            Hidden::make('io')
                                ->helperText(trans('admin/server.block_io_helper'))
                                ->label(trans('admin/server.block_io')),

                            Grid::make()
                                ->columns(4)
                                ->columnSpanFull()
                                ->schema([
                                    ToggleButtons::make('oom_killer')
                                        ->dehydrated()
                                        ->label(trans('admin/server.oom'))
                                        ->formatStateUsing(fn ($state) => $state)
                                        ->inlineLabel()
                                        ->inline()
                                        ->columnSpan(2)
                                        ->stateCast(new BooleanStateCast(false, true))
                                        ->options([
                                            0 => trans('admin/server.disabled'),
                                            1 => trans('admin/server.enabled'),
                                        ])
                                        ->colors([
                                            0 => 'success',
                                            1 => 'danger',
                                        ]),
                                ]),
                        ]),

                    Fieldset::make(trans('admin/server.feature_limits'))
                        ->inlineLabel()
                        ->columnSpanFull()
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 3,
                        ])
                        ->schema([
                            TextInput::make('allocation_limit')
                                ->label(trans('admin/server.allocations'))
                                ->suffixIcon(TablerIcon::Network)
                                ->required()
                                ->minValue(0)
                                ->numeric(),
                            TextInput::make('database_limit')
                                ->label(trans('admin/server.databases'))
                                ->suffixIcon(TablerIcon::Database)
                                ->required()
                                ->minValue(0)
                                ->numeric(),
                            TextInput::make('backup_limit')
                                ->label(trans('admin/server.backups'))
                                ->suffixIcon(TablerIcon::CopyCheck)
                                ->required()
                                ->minValue(0)
                                ->numeric(),
                        ]),
                    Fieldset::make(trans('admin/server.docker_settings'))
                        ->columnSpanFull()
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 4,
                        ])
                        ->schema([
                            Select::make('select_image')
                                ->label(trans('admin/server.image_name'))
                                ->live()
                                ->afterStateUpdated(fn (Set $set, $state) => $set('image', $state))
                                ->options(function ($state, Get $get, Set $set) {
                                    $egg = Egg::query()->find($get('egg_id'));
                                    $images = $egg->docker_images ?? [];

                                    $currentImage = $get('image');
                                    if (!$currentImage && $images) {
                                        $defaultImage = collect($images)->first();
                                        $set('image', $defaultImage);
                                        $set('select_image', $defaultImage);
                                    }

                                    return array_flip($images) + ['ghcr.io/custom-image' => 'Custom Image'];
                                })
                                ->selectablePlaceholder(false)
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 2,
                                    'md' => 3,
                                    'lg' => 2,
                                ]),

                            TextInput::make('image')
                                ->label(trans('admin/server.image'))
                                ->required()
                                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                    $egg = Egg::query()->find($get('egg_id'));
                                    $images = $egg->docker_images ?? [];

                                    if (in_array($state, $images)) {
                                        $set('select_image', $state);
                                    } else {
                                        $set('select_image', 'ghcr.io/custom-image');
                                    }
                                })
                                ->placeholder(trans('admin/server.image_placeholder'))
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 2,
                                    'md' => 3,
                                    'lg' => 2,
                                ]),

                            KeyValue::make('docker_labels')
                                ->live()
                                ->label(trans('admin/server.container_labels'))
                                ->keyLabel(trans('admin/server.title'))
                                ->valueLabel(trans('admin/server.description'))
                                ->columnSpanFull(),
                        ]),
                ]),
            Tab::make('egg')
                ->label(trans('admin/server.egg'))
                ->icon(TablerIcon::Egg)
                ->columns([
                    'default' => 1,
                    'sm' => 3,
                    'md' => 3,
                    'lg' => 5,
                ])
                ->schema([
                    Select::make('egg_id')
                        ->disabled()
                        ->prefixIcon(TablerIcon::Egg)
                        ->columnSpan([
                            'default' => 6,
                            'sm' => 3,
                            'md' => 3,
                            'lg' => 4,
                        ])
                        ->relationship('egg', 'name')
                        ->label(trans('admin/server.name'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->hintAction(
                            Action::make('hint_change_egg')
                                ->label(trans('admin/server.change_egg'))
                                ->action(function (array $data, Server $server, EggChangerService $service, EditServer|ViewServer $livewire) {
                                    $service->handle($server, $data['egg_id'], $data['keep_old_variables']);

                                    // Use redirect instead of fillForm to prevent server variables from duplicating
                                    $livewire->redirect($livewire->getUrl(['record' => $server, 'tab' => 'egg::data::tab']), true);
                                })
                                ->schema(fn (Server $server) => [
                                    Select::make('egg_id')
                                        ->label(trans('admin/server.new_egg'))
                                        ->prefixIcon(TablerIcon::Egg)
                                        ->options(fn () => Egg::all()->filter(fn (Egg $egg) => $egg->id !== $server->egg->id)->mapWithKeys(fn (Egg $egg) => [$egg->id => $egg->name]))
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    Toggle::make('keep_old_variables')
                                        ->label(trans('admin/server.keep_old_variables'))
                                        ->default(true),
                                ])
                                ->hidden(fn (string $operation) => $operation === 'view')
                        ),

                    ToggleButtons::make('skip_scripts')
                        ->label(trans('admin/server.install_script'))
                        ->inline()
                        ->columnSpan([
                            'default' => 6,
                            'sm' => 1,
                            'md' => 1,
                            'lg' => 2,
                        ])
                        ->stateCast(new BooleanStateCast(false, true))
                        ->options([
                            0 => trans('admin/server.yes'),
                            1 => trans('admin/server.skip'),
                        ])
                        ->colors([
                            0 => 'primary',
                            1 => 'danger',
                        ])
                        ->icons([
                            0 => TablerIcon::Code,
                            1 => TablerIcon::CodeOff,
                        ])
                        ->required(),

                    Hidden::make('previewing')
                        ->default(false),

                    Select::make('select_startup')
                        ->label(trans('admin/server.startup_cmd'))
                        ->required()
                        ->live()
                        ->options(function (Get $get) {
                            $egg = Egg::find($get('egg_id'));

                            return array_flip($egg->startup_commands ?? []) + ['custom' => 'Custom Startup'];
                        })
                        ->formatStateUsing(fn (Server $server) => in_array($server->startup, $server->egg->startup_commands) ? $server->startup : 'custom')
                        ->afterStateUpdated(function (Set $set, string $state) {
                            if ($state !== 'custom') {
                                $set('startup', $state);
                            }
                            $set('previewing', false);
                        })
                        ->selectablePlaceholder(false)
                        ->columnSpanFull()
                        ->hintAction(PreviewStartupAction::make('hint_preview')
                            ->hidden(fn (string $operation) => $operation === 'view')),

                    Textarea::make('startup')
                        ->hiddenLabel()
                        ->required()
                        ->live()
                        ->autosize()
                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                            $egg = Egg::find($get('egg_id'));
                            $startups = $egg->startup_commands ?? [];

                            if (in_array($state, $startups)) {
                                $set('select_startup', $state);
                            } else {
                                $set('select_startup', 'custom');
                            }
                        })
                        ->placeholder(trans('admin/server.startup_placeholder'))
                        ->columnSpanFull(),

                    Repeater::make('server_variables')
                        ->hiddenLabel()
                        ->relationship('serverVariables', function (Builder $query, EditServer|ViewServer $livewire) use ($materializeVariables) {
                            if ($materializeVariables) {
                                /** @var Server $server */
                                $server = $livewire->getRecord();
                                $server->ensureVariablesExist();
                            }

                            return $query->orderByPowerJoins('variable.sort');
                        })
                        ->grid()
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                            $data['variable_value'] ??= '';

                            return $data;
                        })
                        ->reorderable(false)->addable(false)->deletable(false)
                        ->schema([
                            StartupVariable::make('variable_value')
                                ->fromRecord()
                                ->disabled(false),
                        ])
                        ->columnSpan(6),
                ]),
            Tab::make('mounts')
                ->label(trans('admin/server.mounts'))
                ->icon(TablerIcon::LayersLinked)
                ->schema(fn (Get $get) => [
                    ServerResource::getMountCheckboxList($get),
                ]),
            Tab::make('actions')
                ->label(trans('admin/server.actions'))
                ->icon(TablerIcon::Settings)
                ->hidden(fn (string $operation) => $operation === 'view')
                ->schema([
                    Fieldset::make(trans('admin/server.actions'))
                        ->columnSpanFull()
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 2,
                            'lg' => 6,
                        ])
                        ->schema([
                            Grid::make()
                                ->columnSpan(3)
                                ->schema([
                                    Actions::make([
                                        Action::make('exclude_toggle_install')
                                            ->label(trans('admin/server.toggle_install'))
                                            ->disabled(fn (Server $server) => $server->isSuspended())
                                            ->modal(fn (Server $server) => $server->isFailedInstall())
                                            ->modalHeading(trans('admin/server.toggle_install_failed_header'))
                                            ->modalDescription(trans('admin/server.toggle_install_failed_desc'))
                                            ->modalSubmitActionLabel(trans('admin/server.reinstall'))
                                            ->action(function (ToggleInstallService $toggleService, ReinstallServerService $reinstallService, Server $server) {
                                                if ($server->isFailedInstall()) {
                                                    try {
                                                        $reinstallService->handle($server);

                                                        Notification::make()
                                                            ->title(trans('admin/server.notifications.reinstall_started'))
                                                            ->success()
                                                            ->send();

                                                    } catch (Exception) {
                                                        Notification::make()
                                                            ->title(trans('admin/server.notifications.reinstall_failed'))
                                                            ->body(trans('admin/server.notifications.error_connecting', ['node' => $server->node->name]))
                                                            ->danger()
                                                            ->send();
                                                    }
                                                } else {
                                                    try {
                                                        $toggleService->handle($server);

                                                        Notification::make()
                                                            ->title(trans('admin/server.notifications.install_toggled'))
                                                            ->success()
                                                            ->send();

                                                    } catch (Exception $exception) {
                                                        Notification::make()
                                                            ->title(trans('admin/server.notifications.install_toggle_failed'))
                                                            ->body($exception->getMessage())
                                                            ->danger()
                                                            ->send();
                                                    }
                                                }
                                            }),
                                    ])->fullWidth(),
                                    ToggleButtons::make('install_help')
                                        ->hiddenLabel()
                                        ->hint(trans('admin/server.toggle_install_help')),
                                ]),
                            Grid::make()
                                ->columnSpan(3)
                                ->schema([
                                    Actions::make([
                                        Action::make('exclude_toggle_suspend')
                                            ->label(trans('admin/server.suspend'))
                                            ->color('warning')
                                            ->hidden(fn (Server $server) => $server->isSuspended())
                                            ->action(function (SuspensionService $suspensionService, Server $server) {
                                                try {
                                                    $suspensionService->handle($server, SuspendAction::Suspend);

                                                    Notification::make()
                                                        ->success()
                                                        ->title(trans('admin/server.notifications.server_suspended'))
                                                        ->send();

                                                } catch (Exception) {
                                                    Notification::make()
                                                        ->warning()
                                                        ->title(trans('admin/server.notifications.server_suspension'))
                                                        ->body(trans('admin/server.notifications.error_connecting', ['node' => $server->node->name]))
                                                        ->send();
                                                }
                                            }),
                                        Action::make('exclude_toggle_unsuspend')
                                            ->label(trans('admin/server.unsuspend'))
                                            ->color('success')
                                            ->hidden(fn (Server $server) => !$server->isSuspended())
                                            ->action(function (SuspensionService $suspensionService, Server $server) {
                                                try {
                                                    $suspensionService->handle($server, SuspendAction::Unsuspend);

                                                    Notification::make()
                                                        ->success()
                                                        ->title(trans('admin/server.notifications.server_unsuspended'))
                                                        ->send();

                                                } catch (Exception) {
                                                    Notification::make()
                                                        ->warning()
                                                        ->title(trans('admin/server.notifications.server_suspension'))
                                                        ->body(trans('admin/server.notifications.error_connecting', ['node' => $server->node->name]))
                                                        ->send();
                                                }
                                            }),
                                    ])->fullWidth(),
                                    ToggleButtons::make('server_suspend')
                                        ->hiddenLabel()
                                        ->hidden(fn (Server $server) => $server->isSuspended())
                                        ->hint(trans('admin/server.notifications.server_suspend_help')),
                                    ToggleButtons::make('server_unsuspend')
                                        ->hiddenLabel()
                                        ->hidden(fn (Server $server) => !$server->isSuspended())
                                        ->hint(trans('admin/server.notifications.server_unsuspend_help')),
                                ]),
                            Grid::make()
                                ->columnSpan(3)
                                ->schema([
                                    Actions::make([
                                        Action::make('exclude_transfer')
                                            ->label(trans('admin/server.transfer'))
                                            ->disabled(fn (Server $server) => user()?->accessibleNodes()->count() <= 1 || $server->isInConflictState())
                                            ->modalHeading(trans('admin/server.transfer'))
                                            ->schema(self::transferServer())
                                            ->action(function (TransferServerService $transfer, BackupAdapterService $backupService, Server $server, $data) {
                                                try {
                                                    $selectedBackupUuids = Arr::get($data, 'backups', []);
                                                    $transfer->handle($server, Arr::get($data, 'node_id'), Arr::get($data, 'allocation_id'), Arr::get($data, 'allocation_additional', []), $selectedBackupUuids);

                                                    $server->backups
                                                        ->whereNotIn('uuid', $selectedBackupUuids)
                                                        ->each(function ($backup) use ($backupService) {
                                                            $schema = $backupService->get($backup->backupHost->schema);

                                                            // Wings backups that aren't transferred only need to be delete on the panel, wings will cleanup the backup files automatically
                                                            if ($schema instanceof WingsBackupSchema) {
                                                                $backup->delete();
                                                            }
                                                        });

                                                    Notification::make()
                                                        ->title(trans('admin/server.notifications.transfer_started'))
                                                        ->success()
                                                        ->send();
                                                } catch (Exception $exception) {
                                                    Notification::make()
                                                        ->title(trans('admin/server.notifications.transfer_failed'))
                                                        ->body($exception->getMessage())
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),
                                    ])->fullWidth(),
                                    ToggleButtons::make('server_transfer')
                                        ->hiddenLabel()
                                        ->hint(new HtmlString(trans('admin/server.transfer_help'))),
                                ]),
                            Grid::make()
                                ->columnSpan(3)
                                ->schema([
                                    Actions::make([
                                        Action::make('exclude_reinstall')
                                            ->label(trans('admin/server.reinstall'))
                                            ->color('danger')
                                            ->requiresConfirmation()
                                            ->modalHeading(trans('admin/server.reinstall_modal_heading'))
                                            ->modalDescription(trans('admin/server.reinstall_modal_description'))
                                            ->disabled(fn (Server $server) => $server->isSuspended())
                                            ->action(function (ReinstallServerService $service, Server $server) {
                                                try {
                                                    $service->handle($server);

                                                    Notification::make()
                                                        ->title(trans('admin/server.notifications.reinstall_started'))
                                                        ->success()
                                                        ->send();
                                                } catch (Exception) {
                                                    Notification::make()
                                                        ->title(trans('admin/server.notifications.reinstall_failed'))
                                                        ->body(trans('admin/server.notifications.error_connecting', ['node' => $server->node->name]))
                                                        ->danger()
                                                        ->send();
                                                }
                                            }),
                                    ])->fullWidth(),
                                    ToggleButtons::make('server_reinstall')
                                        ->hiddenLabel()
                                        ->hint(trans('admin/server.reinstall_help')),
                                ]),
                        ]),
                ]),
        ];
    }

    /**
     * @return Component[]
     *
     * @throws Exception
     */
    private static function transferServer(): array
    {
        return [
            Select::make('node_id')
                ->label(trans('admin/server.node'))
                ->prefixIcon(TablerIcon::Server2)
                ->selectablePlaceholder(false)
                ->default(fn (Server $server) => user()?->accessibleNodes()->whereNot('id', $server->node->id)->first()?->id)
                ->required()
                ->live()
                ->options(fn (Server $server) => user()?->accessibleNodes()->whereNot('id', $server->node->id)->pluck('name', 'id')->all()),
            Select::make('allocation_id')
                ->label(trans('admin/server.primary_allocation'))
                ->disabled(fn (Get $get, Server $server) => !$get('node_id') || !$server->allocation_id)
                ->required(fn (Server $server) => $server->allocation_id)
                ->prefixIcon(TablerIcon::Network)
                ->options(fn (Get $get) => Allocation::where('node_id', $get('node_id'))->whereNull('server_id')->get()->mapWithKeys(fn (Allocation $allocation) => [$allocation->id => $allocation->address]))
                ->searchable(['ip', 'port', 'ip_alias'])
                ->placeholder(trans('admin/server.select_allocation')),
            Select::make('allocation_additional')
                ->label(trans('admin/server.additional_allocations'))
                ->disabled(fn (Get $get, Server $server) => !$get('node_id') || $server->allocations->count() <= 1)
                ->multiple()
                ->minItems(fn (Select $select) => $select->getMaxItems())
                ->maxItems(fn (Select $select, Server $server) => $select->isDisabled() ? null : $server->allocations->count() - 1)
                ->prefixIcon(TablerIcon::Network)
                ->required(fn (Server $server) => $server->allocations->count() > 1)
                ->options(fn (Get $get) => Allocation::where('node_id', $get('node_id'))->whereNull('server_id')->when($get('allocation_id'), fn ($query) => $query->whereNot('id', $get('allocation_id')))->get()->mapWithKeys(fn (Allocation $allocation) => [$allocation->id => $allocation->address]))
                ->searchable(['ip', 'port', 'ip_alias'])
                ->placeholder(trans('admin/server.select_additional')),
            Grid::make()
                ->columnSpanFull()
                ->schema(fn (BackupAdapterService $backupService) => [
                    CheckboxList::make('backups')
                        ->label(trans('admin/server.backups'))
                        ->bulkToggleable()
                        ->options(fn (Server $server) => $server->backups->filter(fn ($backup) => $backupService->get($backup->backupHost->schema) instanceof WingsBackupSchema)->mapWithKeys(fn ($backup) => [$backup->uuid => $backup->name]))
                        ->columns(fn (Server $record) => (int) ceil($record->backups->filter(fn ($backup) => $backupService->get($backup->backupHost->schema) instanceof WingsBackupSchema)->count() / 4)),
                    Text::make('backup_helper')
                        ->columnSpanFull()
                        ->content(trans('admin/server.warning_backups')),
                ])
                ->hidden(fn (Server $server, BackupAdapterService $backupService) => $server->backups->filter(fn ($backup) => $backupService->get($backup->backupHost->schema) instanceof WingsBackupSchema)->count() === 0),
        ];
    }
}
