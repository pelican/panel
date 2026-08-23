<?php

namespace App\Filament\Admin\Resources\Nodes;

use App\Enums\CustomizationKey;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Nodes\Pages\CreateNode;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Nodes\Pages\ListNodes;
use App\Filament\Admin\Resources\Nodes\Pages\ViewNode;
use App\Filament\Admin\Resources\Nodes\RelationManagers\AllocationsRelationManager;
use App\Filament\Admin\Resources\Nodes\RelationManagers\ServersRelationManager;
use App\Models\Node;
use App\Repositories\Daemon\DaemonSystemRepository;
use App\Services\Helpers\SoftwareVersionService;
use App\Services\Nodes\NodeAutoDeployService;
use App\Services\Nodes\NodeUpdateService;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\StateCasts\BooleanStateCast;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Phiki\Grammar\Grammar;

class NodeResource extends Resource
{
    use CanCustomizePages;
    use CanCustomizeRelations;

    protected static ?string $model = Node::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Server2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return trans('admin/node.nav_title');
    }

    public static function getModelLabel(): string
    {
        return trans('admin/node.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('admin/node.model_label_plural');
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

    /** @return class-string<RelationManager>[] */
    public static function getDefaultRelations(): array
    {
        return [
            AllocationsRelationManager::class,
            ServersRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListNodes::route('/'),
            'create' => CreateNode::route('/create'),
            'view' => ViewNode::route('/{record}'),
            'edit' => EditNode::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query->whereIn('id', user()?->accessibleNodes()->pluck('id'));
    }

    /** @return Tab[] */
    public static function detailTabs(): array
    {
        return [
            Tab::make('overview')
                ->label(trans('admin/node.tabs.overview'))
                ->icon(TablerIcon::ChartAreaLineFilled)
                ->columns([
                    'default' => 4,
                    'sm' => 2,
                    'md' => 4,
                    'lg' => 4,
                ])
                ->schema([
                    Fieldset::make()
                        ->label(trans('admin/node.node_info'))
                        ->columns(4)
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make('wings_version')
                                ->label(trans('admin/node.wings_version'))
                                ->state(fn (Node $node, SoftwareVersionService $versionService) => ($node->systemInformation()['version'] ?? trans('admin/node.unknown')) . ' ' . trans('admin/node.latest', ['version' => $versionService->latestWingsVersion()])),
                            TextEntry::make('cpu_threads')
                                ->label(trans('admin/node.cpu_threads'))
                                ->state(fn (Node $node) => $node->systemInformation()['cpu_count'] ?? 0),
                            TextEntry::make('architecture')
                                ->label(trans('admin/node.architecture'))
                                ->state(fn (Node $node) => $node->systemInformation()['architecture'] ?? trans('admin/node.unknown')),
                            TextEntry::make('kernel')
                                ->label(trans('admin/node.kernel'))
                                ->state(fn (Node $node) => $node->systemInformation()['kernel_version'] ?? trans('admin/node.unknown')),
                        ]),
                    View::make('filament.components.node-cpu-chart')
                        ->columnSpan([
                            'default' => 4,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 2,
                        ]),
                    View::make('filament.components.node-memory-chart')
                        ->columnSpan([
                            'default' => 4,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 2,
                        ]),
                    View::make('filament.components.node-storage-chart')
                        ->columnSpanFull(),
                ]),
            Tab::make('basic_settings')
                ->label(trans('admin/node.tabs.basic_settings'))
                ->icon(TablerIcon::Server)
                ->schema([
                    TextInput::make('fqdn')
                        ->columnSpan(2)
                        ->required()
                        ->autofocus()
                        ->live(debounce: 1500)
                        ->rules(Node::getRulesForField('fqdn'))
                        ->label(fn ($state) => is_ip($state) ? trans('admin/node.ip_address') : trans('admin/node.domain'))
                        ->placeholder(fn ($state) => is_ip($state) ? '192.168.1.1' : 'node.example.com')
                        ->helperText(fn () => request()->isSecure() ? trans('admin/node.fqdn_ssl') : null)
                        ->validationMessages([
                            'prohibited' => trans('admin/node.dns_error'),
                        ])
                        ->prohibited(function ($state, Get $get) {
                            if (!$state) {
                                return true;
                            }

                            if (is_ip($state)) {
                                return false;
                            }

                            $ip = $get('ip');

                            return !is_ip($ip);
                        })
                        ->hintColor(function ($state, Get $get) {
                            if (!$state) {
                                return null;
                            }

                            if (is_ip($state)) {
                                if (request()->isSecure()) {
                                    return 'warning';
                                }
                            } else {
                                $ip = $get('ip');

                                return is_ip($ip) ? 'success' : 'danger';
                            }

                            return null;
                        })
                        ->hint(function ($state, Get $get) {
                            if (!$state) {
                                return null;
                            }

                            if (is_ip($state)) {
                                if (request()->isSecure()) {
                                    return trans('admin/node.ssl_ip');
                                }
                            } else {
                                $ip = $get('ip');

                                return is_ip($ip) ? trans('admin/node.valid') . ': ' . $ip : trans('admin/node.invalid');
                            }

                            return null;
                        })
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            $set('ip', null);

                            if (!$state) {
                                return;
                            }

                            [$subdomain] = str($state)->explode('.', 2);
                            if (!is_numeric($subdomain)) {
                                $set('name', $subdomain);
                            }

                            if (!is_ip($state)) {
                                $ip = get_ip_from_hostname($state);
                                if (is_ip($ip)) {
                                    $set('ip', $ip);
                                } else {
                                    $set('ip', null);
                                }
                            }
                        })
                        ->maxLength(255),
                    Hidden::make('ip')
                        ->saved(false),
                    TextInput::make('daemon_connect')
                        ->columnSpan(1)
                        ->label(fn (Get $get) => $get('connection') === 'https_proxy' ? trans('admin/node.connect_port') : trans('admin/node.port'))
                        ->helperText(fn (Get $get) => $get('connection') === 'https_proxy' ? trans('admin/node.connect_port_help') : trans('admin/node.port_help'))
                        ->minValue(1)
                        ->maxValue(65535)
                        ->default(8080)
                        ->required()
                        ->integer(),
                    TextInput::make('daemon_listen')
                        ->columnSpan(1)
                        ->label(trans('admin/node.listen_port'))
                        ->helperText(trans('admin/node.listen_port_help'))
                        ->minValue(1)
                        ->maxValue(65535)
                        ->default(8080)
                        ->required()
                        ->integer()
                        ->visible(fn (Get $get) => $get('connection') === 'https_proxy'),
                    TextInput::make('name')
                        ->label(trans('admin/node.display_name'))
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 1,
                            'lg' => 2,
                        ])
                        ->required()
                        ->maxLength(100),
                    ToggleButtons::make('connection')
                        ->label(trans('admin/node.ssl'))
                        ->columnSpan(2)
                        ->inline()
                        ->helperText(function () {
                            if (request()->isSecure()) {
                                return trans('admin/node.panel_on_ssl');
                            }

                            return null;
                        })
                        ->disableOptionWhen(fn (string $value) => $value === 'http' && request()->isSecure())
                        ->options([
                            'http' => 'HTTP',
                            'https' => 'HTTPS (SSL)',
                            'https_proxy' => 'HTTPS with (reverse) proxy',
                        ])
                        ->colors([
                            'http' => 'warning',
                            'https' => 'success',
                            'https_proxy' => 'success',
                        ])
                        ->icons([
                            'http' => TablerIcon::LockOpenOff,
                            'https' => TablerIcon::Lock,
                            'https_proxy' => TablerIcon::ShieldLock,
                        ])
                        ->formatStateUsing(fn (Get $get) => $get('scheme') === 'http' ? 'http' : ($get('behind_proxy') ? 'https_proxy' : 'https'))
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function ($state, Set $set) {
                            $set('scheme', $state === 'http' ? 'http' : 'https');
                            $set('behind_proxy', $state === 'https_proxy');

                            $set('daemon_connect', $state === 'https_proxy' ? 443 : 8080);
                            $set('daemon_listen', 8080);
                        }),
                    Hidden::make('scheme')
                        ->default(fn () => request()->isSecure() ? 'https' : 'http'),
                    Hidden::make('behind_proxy')
                        ->default(false),
                ]),
            Tab::make('advanced_settings')
                ->label(trans('admin/node.tabs.advanced_settings'))
                ->columns([
                    'default' => 1,
                    'sm' => 1,
                    'md' => 4,
                    'lg' => 6,
                ])
                ->icon(TablerIcon::ServerCog)
                ->schema([
                    TextInput::make('id')
                        ->label(trans('admin/node.node_id'))
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 1,
                        ])
                        ->disabled(),
                    TextInput::make('uuid')
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 2,
                        ])
                        ->label(trans('admin/node.node_uuid'))
                        ->hintCopy()
                        ->disabled(),
                    TagsInput::make('tags')
                        ->label(trans('admin/node.tags'))
                        ->placeholder('')
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 3,
                        ]),
                    TextInput::make('upload_size')
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 1,
                        ])
                        ->label(trans('admin/node.upload_limit'))
                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/node.upload_limit_help'))
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB'),
                    TextInput::make('daemon_base')
                        ->label(trans('admin/node.daemon_base'))
                        ->placeholder('/var/lib/pelican/volumes')
                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/node.daemon_base_help'))
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 2,
                        ])
                        ->required()
                        ->rule('regex:/^([\/][\d\w.\-\/]+)$/'),
                    TextInput::make('daemon_sftp')
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 1,
                        ])
                        ->label(trans('admin/node.sftp_port'))
                        ->minValue(1)
                        ->maxValue(65535)
                        ->default(2022)
                        ->required()
                        ->integer(),
                    TextInput::make('daemon_sftp_alias')
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 2,
                            'lg' => 2,
                        ])
                        ->label(trans('admin/node.sftp_alias'))
                        ->helperText(trans('admin/node.sftp_alias_help')),
                    ToggleButtons::make('public')
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 1,
                            'lg' => 2,
                        ])
                        ->label(trans('admin/node.use_for_deploy'))
                        ->inline()
                        ->stateCast(new BooleanStateCast(false, true))
                        ->options([
                            1 => trans('admin/node.yes'),
                            0 => trans('admin/node.no'),
                        ])
                        ->colors([
                            1 => 'success',
                            0 => 'danger',
                        ]),
                    ToggleButtons::make('maintenance_mode')
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 1,
                            'lg' => 2,
                        ])
                        ->label(trans('admin/node.maintenance_mode'))
                        ->inline()
                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/node.maintenance_mode_help'))
                        ->stateCast(new BooleanStateCast(false, true))
                        ->options([
                            1 => trans('admin/node.enabled'),
                            0 => trans('admin/node.disabled'),
                        ])
                        ->colors([
                            1 => 'danger',
                            0 => 'success',
                        ]),
                    Grid::make()
                        ->columns([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 3,
                            'lg' => 6,
                        ])
                        ->columnSpanFull()
                        ->schema([
                            ToggleButtons::make('unlimited_mem')
                                ->dehydrated()
                                ->label(trans('admin/node.memory'))->inlineLabel()->inline()
                                ->afterStateUpdated(fn (Set $set) => $set('memory', 0))
                                ->afterStateUpdated(fn (Set $set) => $set('memory_overallocate', 0))
                                ->formatStateUsing(fn (Get $get) => $get('memory') == 0)
                                ->live()
                                ->stateCast(new BooleanStateCast(false, true))
                                ->options([
                                    1 => trans('admin/node.unlimited'),
                                    0 => trans('admin/node.limited'),
                                ])
                                ->colors([
                                    1 => 'primary',
                                    0 => 'warning',
                                ])
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'md' => 1,
                                    'lg' => 2,
                                ]),
                            TextInput::make('memory')
                                ->dehydratedWhenHidden()
                                ->hidden(fn (Get $get) => $get('unlimited_mem'))
                                ->label(trans('admin/node.memory_limit'))->inlineLabel()
                                ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                ->required()
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'md' => 1,
                                    'lg' => 2,
                                ])
                                ->numeric()
                                ->minValue(0),
                            TextInput::make('memory_overallocate')
                                ->dehydratedWhenHidden()
                                ->label(trans('admin/node.overallocate'))->inlineLabel()
                                ->required()
                                ->hidden(fn (Get $get) => $get('unlimited_mem'))
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'md' => 1,
                                    'lg' => 2,
                                ])
                                ->numeric()
                                ->minValue(-1)
                                ->maxValue(100)
                                ->suffix('%'),
                        ]),
                    Grid::make()
                        ->columnSpanFull()
                        ->columns([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 3,
                            'lg' => 6,
                        ])
                        ->schema([
                            ToggleButtons::make('unlimited_disk')
                                ->dehydrated()
                                ->label(trans('admin/node.disk'))->inlineLabel()->inline()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('disk', 0))
                                ->afterStateUpdated(fn (Set $set) => $set('disk_overallocate', 0))
                                ->formatStateUsing(fn (Get $get) => $get('disk') == 0)
                                ->stateCast(new BooleanStateCast(false, true))
                                ->options([
                                    1 => trans('admin/node.unlimited'),
                                    0 => trans('admin/node.limited'),
                                ])
                                ->colors([
                                    1 => 'primary',
                                    0 => 'warning',
                                ])
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'md' => 1,
                                    'lg' => 2,
                                ]),
                            TextInput::make('disk')
                                ->dehydratedWhenHidden()
                                ->hidden(fn (Get $get) => $get('unlimited_disk'))
                                ->label(trans('admin/node.disk_limit'))->inlineLabel()
                                ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                ->required()
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'md' => 1,
                                    'lg' => 2,
                                ])
                                ->numeric()
                                ->minValue(0),
                            TextInput::make('disk_overallocate')
                                ->dehydratedWhenHidden()
                                ->hidden(fn (Get $get) => $get('unlimited_disk'))
                                ->label(trans('admin/node.overallocate'))->inlineLabel()
                                ->columnSpan([
                                    'default' => 1,
                                    'sm' => 1,
                                    'md' => 1,
                                    'lg' => 2,
                                ])
                                ->required()
                                ->numeric()
                                ->minValue(-1)
                                ->maxValue(100)
                                ->suffix('%'),
                        ]),
                    Grid::make()
                        ->columns(6)
                        ->columnSpanFull()
                        ->schema([
                            ToggleButtons::make('unlimited_cpu')
                                ->dehydrated()
                                ->label(trans('admin/node.cpu'))->inlineLabel()->inline()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('cpu', 0))
                                ->afterStateUpdated(fn (Set $set) => $set('cpu_overallocate', 0))
                                ->formatStateUsing(fn (Get $get) => $get('cpu') == 0)
                                ->stateCast(new BooleanStateCast(false, true))
                                ->options([
                                    1 => trans('admin/node.unlimited'),
                                    0 => trans('admin/node.limited'),
                                ])
                                ->colors([
                                    1 => 'primary',
                                    0 => 'warning',
                                ])
                                ->columnSpan(2),
                            TextInput::make('cpu')
                                ->dehydratedWhenHidden()
                                ->hidden(fn (Get $get) => $get('unlimited_cpu'))
                                ->label(trans('admin/node.cpu_limit'))->inlineLabel()
                                ->suffix('%')
                                ->required()
                                ->columnSpan(2)
                                ->numeric()
                                ->minValue(0),
                            TextInput::make('cpu_overallocate')
                                ->dehydratedWhenHidden()
                                ->hidden(fn (Get $get) => $get('unlimited_cpu'))
                                ->label(trans('admin/node.overallocate'))->inlineLabel()
                                ->columnSpan(2)
                                ->required()
                                ->numeric()
                                ->minValue(-1)
                                ->maxValue(100)
                                ->suffix('%'),
                        ]),
                ]),
            Tab::make('config_file')
                ->label(trans('admin/node.tabs.config_file'))
                ->icon(TablerIcon::Code)
                ->hidden(fn (string $operation) => $operation === 'view')
                ->schema([
                    TextEntry::make('instructions')
                        ->label(trans('admin/node.instructions'))
                        ->columnSpanFull()
                        ->state(new HtmlString(trans('admin/node.instructions_help'))),
                    CodeEntry::make('config')
                        ->label('/etc/pelican/config.yml')
                        ->grammar(Grammar::Yaml)
                        ->state(fn (Node $node) => $node->getYamlConfiguration())
                        ->copyable()
                        ->disabled()
                        ->columnSpanFull(),
                    Grid::make()
                        ->columns()
                        ->columnSpanFull()
                        ->schema([
                            Actions::make([
                                Action::make('exclude_autoDeploy')
                                    ->label(trans('admin/node.auto_deploy'))
                                    ->color('primary')
                                    ->modalHeading(trans('admin/node.auto_deploy'))
                                    ->icon(TablerIcon::Rocket)
                                    ->modalSubmitAction(false)
                                    ->modalCancelAction(false)
                                    ->modalFooterActionsAlignment(Alignment::Center)
                                    ->schema([
                                        ToggleButtons::make('docker')
                                            ->label(trans('admin/node.auto_label'))
                                            ->live()
                                            ->helperText(trans('admin/node.auto_question'))
                                            ->inline()
                                            ->default(false)
                                            ->afterStateUpdated(fn (bool $state, NodeAutoDeployService $service, Node $node, Set $set) => $set('generatedToken', $service->handle(request(), $node, $state)))
                                            ->stateCast(new BooleanStateCast(false, true))
                                            ->options([
                                                0 => trans('admin/node.standalone'),
                                                1 => trans('admin/node.docker'),
                                            ])
                                            ->colors([
                                                0 => 'primary',
                                                1 => 'success',
                                            ])
                                            ->columnSpan(1),
                                        Textarea::make('generatedToken')
                                            ->label(trans('admin/node.auto_command'))
                                            ->readOnly()
                                            ->autosize()
                                            ->hintCopy()
                                            ->formatStateUsing(fn (NodeAutoDeployService $service, Node $node, Set $set, Get $get) => $set('generatedToken', $service->handle(request(), $node, $get('docker')))),
                                    ])
                                    ->mountUsing(function (Schema $schema) {
                                        $schema->fill();
                                    })
                                    ->hidden(fn (string $operation) => $operation === 'view'),
                            ])->fullWidth(),
                            Actions::make([
                                Action::make('exclude_resetKey')
                                    ->label(trans('admin/node.reset_token'))
                                    ->color('danger')
                                    ->requiresConfirmation()
                                    ->modalHeading(trans('admin/node.reset_token'))
                                    ->modalDescription(trans('admin/node.reset_help'))
                                    ->action(function (Node $node, NodeUpdateService $service, EditNode|ViewNode $livewire) {
                                        try {
                                            $service->handle($node, [], true);
                                        } catch (Exception) {
                                            Notification::make()
                                                ->title(trans('admin/node.error_connecting', ['node' => $node->name]))
                                                ->body(trans('admin/node.error_connecting_description'))
                                                ->color('warning')
                                                ->icon(TablerIcon::Database)
                                                ->warning()
                                                ->send();

                                        }
                                        Notification::make()->success()->title(trans('admin/node.token_reset'))->send();
                                        $livewire->refreshFormData(['daemon_token_id', 'daemon_token']);
                                    })
                                    ->hidden(fn (string $operation) => $operation === 'view'),
                            ])->fullWidth(),
                        ]),
                ]),
            Tab::make('diagnostics')
                ->label(trans('admin/node.tabs.diagnostics'))
                ->icon(TablerIcon::HeartSearch)
                ->hidden(fn (string $operation) => $operation === 'view')
                ->schema([
                    Section::make('diag')
                        ->heading(trans('admin/node.tabs.diagnostics'))
                        ->columnSpanFull()
                        ->columns(4)
                        ->disabled(fn (Get $get) => $get('pulled'))
                        ->headerActions([
                            Action::make('pull')
                                ->label(trans('admin/node.diagnostics.pull'))
                                ->tooltip(trans('admin/node.diagnostics.pull'))
                                ->icon(TablerIcon::CloudDownload)
                                ->hidden(fn (Get $get) => $get('pulled'))
                                ->action(function (Get $get, Set $set, Node $node, DaemonSystemRepository $daemon) {
                                    $includeEndpoints = $get('include_endpoints') ?? true;
                                    $includeLogs = $get('include_logs') ?? true;
                                    $logLines = $get('log_lines') ?? 200;

                                    try {
                                        $response = $daemon->setNode($node)->getDiagnostics($logLines, $includeEndpoints, $includeLogs);

                                        if ($response->status() === 404) {
                                            Notification::make()
                                                ->title(trans('admin/node.diagnostics.404'))
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $set('pulled', true);
                                        $set('uploaded', false);
                                        $set('log', $response->body());

                                        Notification::make()
                                            ->title(trans('admin/node.diagnostics.logs_pulled'))
                                            ->success()
                                            ->send();
                                    } catch (ConnectionException $e) {
                                        Notification::make()
                                            ->title(trans('admin/node.error_connecting', ['node' => $node->name]))
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();

                                    }
                                }),
                            Action::make('upload')
                                ->tooltip(trans('admin/node.diagnostics.upload'))
                                ->visible(fn (Get $get) => $get('pulled') ?? false)
                                ->icon(TablerIcon::CloudUpload)
                                ->action(function (Get $get, Set $set) {
                                    try {
                                        $response = Http::asMultipart()
                                            ->attach('c', $get('log'))
                                            ->attach('e', '14d')
                                            ->post('https://logs.pelican.dev');

                                        if ($response->failed()) {
                                            Notification::make()
                                                ->title(trans('admin/node.diagnostics.upload_failed'))
                                                ->body(fn () => $response->status() . ' - ' . $response->body())
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        $data = $response->json();
                                        $url = $data['url'];

                                        Notification::make()
                                            ->title(trans('admin/node.diagnostics.logs_uploaded'))
                                            ->body("{$url}")
                                            ->success()
                                            ->actions([
                                                Action::make('exclude_viewLogs')
                                                    ->label(trans('admin/node.diagnostics.view_logs'))
                                                    ->url($url)
                                                    ->openUrlInNewTab(true),
                                            ])
                                            ->persistent()
                                            ->send();
                                        $set('log', $url);
                                        $set('pulled', false);
                                        $set('uploaded', true);

                                    } catch (Exception $e) {
                                        Notification::make()
                                            ->title(trans('admin/node.diagnostics.upload_failed'))
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),
                            Action::make('clear')
                                ->tooltip(trans('admin/node.diagnostics.clear'))
                                ->visible(fn (Get $get) => $get('pulled') ?? false)
                                ->icon(TablerIcon::Trash)->color('danger')
                                ->action(function (Get $get, Set $set, EditNode|ViewNode $livewire) {
                                    $set('pulled', false);
                                    $set('uploaded', false);
                                    $set('log', null);
                                    $livewire->refresh();
                                }
                                ),
                        ])
                        ->schema([
                            ToggleButtons::make('include_endpoints')
                                ->label(trans('admin/node.diagnostics.include_endpoints'))
                                ->hintIcon(TablerIcon::QuestionMark)->inline()
                                ->hintIconTooltip(trans('admin/node.diagnostics.include_endpoints_hint'))
                                ->formatStateUsing(fn () => 1)
                                ->boolean(),
                            ToggleButtons::make('include_logs')
                                ->label(trans('admin/node.diagnostics.include_logs'))
                                ->live()
                                ->hintIcon(TablerIcon::QuestionMark)->inline()
                                ->hintIconTooltip(trans('admin/node.diagnostics.include_logs_hint'))
                                ->formatStateUsing(fn () => 1)
                                ->boolean(),
                            Slider::make('log_lines')
                                ->label(trans('admin/node.diagnostics.log_lines'))
                                ->columnSpan(2)
                                ->hiddenLabel()
                                ->live()
                                ->tooltips(RawJs::make(<<<'JS'
                                            `${$value} lines`
                                            JS))
                                ->visible(fn (Get $get) => $get('include_logs'))
                                ->range(minValue: 100, maxValue: 500)
                                ->pips(PipsMode::Steps, density: 10)
                                ->step(50)
                                ->formatStateUsing(fn () => 200)
                                ->fillTrack(),
                            Hidden::make('pulled'),
                            Hidden::make('uploaded'),
                        ]),
                    Textarea::make('log')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->rows(35)
                        ->visible(fn (Get $get) => ($get('pulled') ?? false) || ($get('uploaded') ?? false)),
                ]),
        ];
    }
}
