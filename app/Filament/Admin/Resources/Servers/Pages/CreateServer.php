<?php

namespace App\Filament\Admin\Resources\Servers\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Servers\ServerResource;
use App\Filament\Components\Forms\Fields\StartupVariable;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\User;
use App\Services\Allocations\AssignmentService;
use App\Services\Servers\RandomWordService;
use App\Services\Servers\ServerCreationService;
use App\Services\Users\UserCreationService;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\CanCustomizeSteps;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use LogicException;
use Random\RandomException;

class CreateServer extends CreateRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use CanCustomizeSteps;

    protected static string $resource = ServerResource::class;

    protected static bool $canCreateAnother = false;

    public ?Node $node = null;

    private ServerCreationService $serverCreationService;

    public function boot(ServerCreationService $serverCreationService): void
    {
        $this->serverCreationService = $serverCreationService;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make($this->getSteps())
                    ->columnSpanFull()
                    ->nextAction(fn (Action $action) => $action->tooltip(fn () => $action->getLabel())->iconButton()->iconSize(IconSize::ExtraLarge)->icon(TablerIcon::ArrowRight))
                    ->previousAction(fn (Action $action) => $action->tooltip(fn () => $action->getLabel())->iconButton()->iconSize(IconSize::ExtraLarge)->icon(TablerIcon::ArrowLeft))
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::icon-button
                            type="submit"
                            iconSize="xl"
                            icon="tabler-plus"
                            tooltip="{{ trans('admin/server.create') }}"
                        >
                            {{ trans('admin/server.create') }}
                        </x-filament::icon-button>
                    BLADE))),
            ]);
    }

    /**
     * @return Step[]
     *
     * @throws RandomException
     */
    protected function getDefaultSteps(): array
    {
        return [
            Step::make('Information')
                ->label(trans('admin/server.tabs.information'))
                ->icon(TablerIcon::InfoCircle)
                ->completedIcon(TablerIcon::Check)
                ->columns([
                    'default' => 1,
                    'sm' => 4,
                    'md' => 4,
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
                            }))
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 2,
                        ])
                        ->required()
                        ->maxLength(255),

                    TextInput::make('external_id')
                        ->label(trans('admin/server.external_id'))
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 2,
                        ])
                        ->unique()
                        ->maxLength(255),

                    Select::make('node_id')
                        ->disabledOn('edit')
                        ->prefixIcon(TablerIcon::Server2)
                        ->selectablePlaceholder(false)
                        ->default(function () {
                            $lastUsedNode = session()->get('last_utilized_node');

                            if ($lastUsedNode && user()?->accessibleNodes()->where('id', $lastUsedNode)->exists()) {
                                $this->node = Node::find($lastUsedNode);

                                return $this->node?->id;
                            }

                            /** @var ?Node $latestNode */
                            $latestNode = user()?->accessibleNodes()->latest()->first();
                            $this->node = $latestNode;

                            return $this->node?->id;
                        })
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 2,
                        ])
                        ->live()
                        ->relationship('node', 'name', fn (Builder $query) => $query->whereIn('id', user()?->accessibleNodes()->pluck('id')))
                        ->searchable()
                        ->required()
                        ->preload()
                        ->afterStateUpdated(function (Set $set, $state) {
                            $set('allocation_id', null);
                            $this->node = Node::find($state);
                        }),

                    Select::make('owner_id')
                        ->preload()
                        ->prefixIcon(TablerIcon::User)
                        ->selectablePlaceholder(false)
                        ->default(user()?->id)
                        ->label(trans('admin/server.owner'))
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 2,
                        ])
                        ->relationship('user', 'username')
                        ->searchable(['username', 'email'])
                        ->getOptionLabelFromRecordUsing(fn (User $user) => "$user->username ($user->email)")
                        ->createOptionAction(fn (Action $action) => $action->authorize(fn () => user()?->can('create', User::class)))
                        ->createOptionForm([
                            TextInput::make('username')
                                ->label(trans('admin/user.username'))
                                ->alphaNum()
                                ->required()
                                ->minLength(3)
                                ->maxLength(255),

                            TextInput::make('email')
                                ->label(trans('admin/user.email'))
                                ->email()
                                ->required()
                                ->unique()
                                ->maxLength(255),

                            TextInput::make('password')
                                ->label(trans('admin/user.password'))
                                ->hintIcon(TablerIcon::QuestionMark, trans('admin/user.password_help'))
                                ->password(),
                        ])
                        ->createOptionUsing(function ($data, UserCreationService $service) {
                            $service->handle($data);

                            $this->refreshForm();
                        })
                        ->required(),

                    Select::make('allocation_id')
                        ->preload()
                        ->live()
                        ->prefixIcon(TablerIcon::Network)
                        ->label(trans('admin/server.primary_allocation'))
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 2,
                        ])
                        ->disabled(fn (Get $get) => $get('node_id') === null)
                        ->searchable(['ip', 'port', 'ip_alias'])
                        ->afterStateUpdated(function (Set $set) {
                            $set('allocation_additional', null);
                            $set('allocation_additional.needstobeastringhere.extra_allocations', null);
                        })
                        ->getOptionLabelFromRecordUsing(fn (Allocation $allocation) => $allocation->address ?? '')
                        ->placeholder(function (Get $get) {
                            $node = Node::find($get('node_id'));

                            if ($node?->allocations) {
                                return trans('admin/server.select_allocation');
                            }

                            return trans('admin/server.new_allocation');
                        })
                        ->relationship(
                            'allocation',
                            'ip',
                            fn (Builder $query, Get $get) => $query
                                ->where('node_id', $get('node_id'))
                                ->whereNull('server_id'),
                        )
                        ->createOptionAction(fn (Action $action) => $action->authorize(fn () => user()?->can('create', Allocation::class)))
                        ->createOptionForm(function (Get $get) {
                            $getPage = $get;

                            return [
                                Select::make('allocation_ip')
                                    ->options(fn (Get $get) => collect(Node::find($getPage('node_id'))?->ipAddresses())
                                        ->when($get('allocation_ip'), fn ($ips, $current) => $ips->push($current))
                                        ->unique()
                                        ->mapWithKeys(fn (string $ip) => [$ip => $ip]))
                                    ->label(trans('admin/server.ip_address'))->inlineLabel()
                                    ->helperText(trans('admin/server.ip_address_helper'))
                                    ->afterStateUpdated(fn (Set $set) => $set('allocation_ports', []))
                                    ->ip()
                                    ->live()
                                    ->hintAction(
                                        Action::make('hint_refresh')
                                            ->iconButton()
                                            ->icon(TablerIcon::Refresh)
                                            ->tooltip(trans('admin/node.refresh'))
                                            ->action(function () use ($get) {
                                                cache()->forget("nodes.{$get('node_id')}.ips");
                                            })
                                    )
                                    ->suffixAction(
                                        Action::make('custom_ip')
                                            ->icon(TablerIcon::Keyboard)
                                            ->tooltip(trans('admin/node.custom_ip'))
                                            ->schema([
                                                TextInput::make('custom_ip')
                                                    ->label(trans('admin/node.ip_address'))
                                                    ->ip()
                                                    ->required(),
                                            ])
                                            ->action(fn (array $data, Set $set) => $set('allocation_ip', $data['custom_ip']))
                                    )
                                    ->required(),
                                TextInput::make('allocation_alias')
                                    ->label(trans('admin/server.alias'))->inlineLabel()
                                    ->default(null)
                                    ->datalist([
                                        $get('name'),
                                        Egg::find($get('egg_id'))?->name,
                                    ])
                                    ->helperText(trans('admin/server.alias_helper')),
                                TagsInput::make('allocation_ports')
                                    ->label(trans('admin/server.port'))->inlineLabel()
                                    ->placeholder('27015, 27017-27019')
                                    ->live()
                                    ->disabled(fn (Get $get) => empty($get('allocation_ip')))
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('allocation_ports',
                                        CreateServer::retrieveValidPorts(Node::find($getPage('node_id')), $state, $get('allocation_ip')))
                                    )
                                    ->splitKeys(['Tab', ' ', ','])
                                    ->required(),
                            ];
                        })
                        ->createOptionUsing(function (array $data, Get $get, AssignmentService $assignmentService): int {
                            return collect(
                                $assignmentService->handle(Node::find($get('node_id')), $data)
                            )->firstOrFail();
                        }),
                    Repeater::make('allocation_additional')
                        ->label(trans('admin/server.additional_allocations'))
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 2,
                        ])
                        ->addActionLabel('Add Allocation')
                        ->disabled(fn (Get $get) => $get('allocation_id') === null)
                        // ->addable() TODO disable when all allocations are taken
                        // ->addable() TODO disable until first additional allocation is selected
                        ->simple(
                            Select::make('extra_allocations')
                                ->live()
                                ->preload()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->prefixIcon(TablerIcon::Network)
                                ->label(trans('admin/server.additional_allocations'))
                                ->columnSpan(2)
                                ->disabled(fn (Get $get) => $get('../../allocation_id') === null || $get('../../node_id') === null)
                                ->searchable(['ip', 'port', 'ip_alias'])
                                ->getOptionLabelFromRecordUsing(fn (Allocation $allocation) => $allocation->address)
                                ->placeholder(trans('admin/server.select_additional'))
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->relationship(
                                    'allocations',
                                    'ip',
                                    fn (Builder $query, Get $get, Select $component, $state) => $query
                                        ->where('node_id', $get('../../node_id'))
                                        ->whereNot('id', $get('../../allocation_id'))
                                        ->whereNull('server_id'),
                                ),
                        ),

                    Textarea::make('description')
                        ->label(trans('admin/server.description'))
                        ->rows(3)
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 4,
                            'md' => 4,
                        ]),
                ]),

            Step::make(trans('admin/server.tabs.egg_configuration'))
                ->icon(TablerIcon::Egg)
                ->completedIcon(TablerIcon::Check)
                ->columns([
                    'default' => 1,
                    'sm' => 4,
                    'md' => 4,
                    'lg' => 6,
                ])
                ->schema([
                    Select::make('egg_id')
                        ->label(trans('admin/server.name'))
                        ->prefixIcon(TablerIcon::Egg)
                        ->relationship('egg', 'name')
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 2,
                            'lg' => 4,
                        ])
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get, $old) {
                            $egg = Egg::query()->find($state);
                            $set('startup', '');
                            $set('image', '');

                            $variables = $egg->variables ?? [];
                            $serverVariables = collect();
                            foreach ($variables as $variable) {
                                $serverVariables->add($variable->toArray());
                            }

                            $variables = [];
                            $set($path = 'server_variables', $serverVariables->sortBy(['sort'])->all());
                            for ($i = 0; $i < $serverVariables->count(); $i++) {
                                $set("$path.$i.variable_value", $serverVariables[$i]['default_value']);
                                $set("$path.$i.variable_id", $serverVariables[$i]['id']);
                                $variables[$serverVariables[$i]['env_variable']] = $serverVariables[$i]['default_value'];
                            }

                            $set('environment', $variables);

                            $previousEgg = Egg::query()->find($old);
                            if (!$get('name') || $previousEgg?->getKebabName() === $get('name')) {
                                $set('name', $egg->getKebabName());
                            }
                        })
                        ->required(),

                    ToggleButtons::make('skip_scripts')
                        ->label(trans('admin/server.install_script'))
                        ->default(false)
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 1,
                            'lg' => 1,
                        ])
                        ->options([
                            false => trans('admin/server.yes'),
                            true => trans('admin/server.skip'),
                        ])
                        ->colors([
                            false => 'primary',
                            true => 'danger',
                        ])
                        ->icons([
                            false => TablerIcon::Code,
                            true => TablerIcon::CodeOff,
                        ])
                        ->inline()
                        ->required(),

                    ToggleButtons::make('start_on_completion')
                        ->label(trans('admin/server.start_after'))
                        ->default(true)
                        ->required()
                        ->columnSpan([
                            'default' => 1,
                            'sm' => 1,
                            'md' => 1,
                            'lg' => 1,
                        ])
                        ->options([
                            true => trans('admin/server.yes'),
                            false => trans('admin/server.no'),
                        ])
                        ->colors([
                            true => 'primary',
                            false => 'danger',
                        ])
                        ->icons([
                            true => TablerIcon::Code,
                            false => TablerIcon::CodeOff,
                        ])
                        ->inline(),

                    Select::make('select_startup')
                        ->label(trans('admin/server.startup_cmd'))
                        ->hidden(fn (Get $get) => $get('egg_id') === null)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set, $state) => $set('startup', $state))
                        ->options(function ($state, Get $get, Set $set) {
                            $egg = Egg::query()->find($get('egg_id'));
                            $startups = $egg->startup_commands ?? [];

                            $currentStartup = $get('startup');
                            if (!$currentStartup && $startups) {
                                $currentStartup = collect($startups)->first();
                                $set('startup', $currentStartup);
                                $set('select_startup', $currentStartup);
                            }

                            return array_flip($startups) + ['custom' => 'Custom Startup'];
                        })
                        ->selectablePlaceholder(false)
                        ->columnSpanFull(),

                    Textarea::make('startup')
                        ->hiddenLabel()
                        ->hidden(fn (Get $get) => $get('egg_id') === null)
                        ->required()
                        ->live()
                        ->autosize()
                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                            $egg = Egg::query()->find($get('egg_id'));
                            $startups = $egg->startup_commands ?? [];

                            if (in_array($state, $startups)) {
                                $set('select_startup', $state);
                            } else {
                                $set('select_startup', 'custom');
                            }
                        })
                        ->placeholder(trans('admin/server.startup_placeholder'))
                        ->columnSpanFull(),

                    Hidden::make('environment')->default([]),

                    Section::make(trans('admin/server.variables'))
                        ->icon(TablerIcon::Eggs)
                        ->iconColor('primary')
                        ->hidden(fn (Get $get) => $get('egg_id') === null)
                        ->collapsible()
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make(trans('admin/server.select_egg'))
                                ->hidden(fn (Get $get) => $get('egg_id')),
                            TextEntry::make(trans('admin/server.no_variables'))
                                ->hidden(fn (Get $get) => !$get('egg_id') ||
                                    Egg::query()->find($get('egg_id'))?->variables()?->count()
                                ),
                            Repeater::make('server_variables')
                                ->hiddenLabel()
                                ->relationship('serverVariables', fn (Builder $query) => $query->orderByPowerJoins('variable.sort'))
                                ->saveRelationshipsBeforeChildrenUsing(null)
                                ->saveRelationshipsUsing(null)
                                ->grid(2)
                                ->reorderable(false)
                                ->addable(false)
                                ->deletable(false)
                                ->default([])
                                ->hidden(fn ($state) => empty($state))
                                ->schema([
                                    StartupVariable::make('variable_value')
                                        ->fromForm()
                                        ->disabled(false)
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            $environment = $get($envPath = '../../environment');
                                            $environment[$get('env_variable')] = $state;
                                            $set($envPath, $environment);
                                        }),
                                ])
                                ->columnSpan(2),
                        ]),
                ]),
            Step::make(trans('admin/server.tabs.environment_configuration'))
                ->icon(TablerIcon::BrandDocker)
                ->completedIcon(TablerIcon::Check)
                ->schema([
                    Fieldset::make(trans('admin/server.resource_limits'))
                        ->columnSpan(6)
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
                                        ->default(true)
                                        ->afterStateUpdated(fn (Set $set) => $set('cpu', 0))
                                        ->live()
                                        ->options([
                                            true => trans('admin/server.unlimited'),
                                            false => trans('admin/server.limited'),
                                        ])
                                        ->colors([
                                            true => 'primary',
                                            false => 'warning',
                                        ])
                                        ->columnSpan(2),

                                    TextInput::make('cpu')
                                        ->dehydratedWhenHidden()
                                        ->hidden(fn (Get $get) => $get('unlimited_cpu'))
                                        ->label(trans('admin/server.cpu_limit'))->inlineLabel()
                                        ->suffix('%')
                                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/server.cpu_helper'))
                                        ->default(0)
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
                                        ->default(true)
                                        ->afterStateUpdated(fn (Set $set) => $set('memory', 0))
                                        ->live()
                                        ->options([
                                            true => trans('admin/server.unlimited'),
                                            false => trans('admin/server.limited'),
                                        ])
                                        ->colors([
                                            true => 'primary',
                                            false => 'warning',
                                        ])
                                        ->columnSpan(2),

                                    TextInput::make('memory')
                                        ->dehydratedWhenHidden()
                                        ->hidden(fn (Get $get) => $get('unlimited_mem'))
                                        ->label(trans('admin/server.memory_limit'))->inlineLabel()
                                        ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/server.memory_helper'))
                                        ->default(0)
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
                                        ->default(true)
                                        ->live()
                                        ->afterStateUpdated(fn (Set $set) => $set('disk', 0))
                                        ->options([
                                            true => trans('admin/server.unlimited'),
                                            false => trans('admin/server.limited'),
                                        ])
                                        ->colors([
                                            true => 'primary',
                                            false => 'warning',
                                        ])
                                        ->columnSpan(2),

                                    TextInput::make('disk')
                                        ->dehydratedWhenHidden()
                                        ->hidden(fn (Get $get) => $get('unlimited_disk'))
                                        ->label(trans('admin/server.disk_limit'))->inlineLabel()
                                        ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                        ->default(0)
                                        ->required()
                                        ->columnSpan(2)
                                        ->numeric()
                                        ->minValue(0),
                                ]),

                        ]),

                    Fieldset::make(trans('admin/server.advanced_limits'))
                        ->columnSpan(6)
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 3,
                        ])
                        ->schema([
                            Hidden::make('io')
                                ->helperText(trans('admin/server.block_io_helper'))
                                ->label(trans('admin/server.block_io'))
                                ->default(500),

                            Grid::make()
                                ->columns(4)
                                ->columnSpanFull()
                                ->schema([
                                    ToggleButtons::make('cpu_pinning')
                                        ->label(trans('admin/server.cpu_pin'))->inlineLabel()->inline()
                                        ->default(false)
                                        ->afterStateUpdated(fn (Set $set) => $set('threads', []))
                                        ->live()
                                        ->options([
                                            false => trans('admin/server.disabled'),
                                            true => trans('admin/server.enabled'),
                                        ])
                                        ->colors([
                                            false => 'success',
                                            true => 'warning',
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
                            Grid::make()
                                ->columns(4)
                                ->columnSpanFull()
                                ->schema([
                                    ToggleButtons::make('swap_support')
                                        ->live()
                                        ->label(trans('admin/server.swap'))
                                        ->inlineLabel()
                                        ->inline()
                                        ->columnSpan(2)
                                        ->default('disabled')
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            $value = match ($state) {
                                                'unlimited' => -1,
                                                'disabled' => 0,
                                                'limited' => 128,
                                                default => throw new LogicException('Invalid state'),
                                            };

                                            $set('swap', $value);
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
                                            'disabled', 'unlimited' => true,
                                            default => false,
                                        })
                                        ->label(trans('admin/server.swap_limit'))
                                        ->default(0)
                                        ->suffix(config('panel.use_binary_prefix') ? 'MiB' : 'MB')
                                        ->minValue(-1)
                                        ->columnSpan(2)
                                        ->inlineLabel()
                                        ->required()
                                        ->integer(),
                                ]),

                            Grid::make()
                                ->columns(4)
                                ->columnSpanFull()
                                ->schema([
                                    ToggleButtons::make('oom_killer')
                                        ->label(trans('admin/server.oom'))
                                        ->inlineLabel()->inline()
                                        ->default(false)
                                        ->columnSpan(2)
                                        ->options([
                                            false => trans('admin/server.disabled'),
                                            true => trans('admin/server.enabled'),
                                        ])
                                        ->colors([
                                            false => 'success',
                                            true => 'danger',
                                        ]),
                                ]),
                        ]),

                    Fieldset::make(trans('admin/server.feature_limits'))
                        ->inlineLabel()
                        ->columnSpan(6)
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
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                            TextInput::make('database_limit')
                                ->label(trans('admin/server.databases'))
                                ->suffixIcon(TablerIcon::Database)
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                            TextInput::make('backup_limit')
                                ->label(trans('admin/server.backups'))
                                ->suffixIcon(TablerIcon::CopyCheck)
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ]),
                    Fieldset::make(trans('admin/server.docker_settings'))
                        ->columns([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'lg' => 4,
                        ])
                        ->columnSpan(6)
                        ->schema(fn (Get $get) => [
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

                            ServerResource::getMountCheckboxList($get),
                        ]),
                ]),
        ];
    }

    public function refreshForm(): void
    {
        $this->fillForm();
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function handleRecordCreation(array $data): Model
    {
        if ($allocation_additional = array_get($data, 'allocation_additional')) {
            $data['allocation_additional'] = collect($allocation_additional)->filter()->all();
        }

        session()->put('last_utilized_node', $data['node_id']);

        try {
            return $this->serverCreationService->handle($data);
        } catch (Exception $exception) {
            Notification::make()
                ->title(trans('admin/server.notifications.create_failed'))
                ->body($exception->getMessage())
                ->color('danger')
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    /**
     * @param  string[]  $portEntries
     * @return array<int>
     */
    public static function retrieveValidPorts(Node $node, array $portEntries, string $ip): array
    {
        $portRangeLimit = AssignmentService::PORT_RANGE_LIMIT;
        $portFloor = AssignmentService::PORT_FLOOR;
        $portCeil = AssignmentService::PORT_CEIL;

        $ports = collect();

        $existingPorts = $node
            ->allocations()
            ->where('ip', $ip)
            ->pluck('port')
            ->all();

        foreach ($portEntries as $portEntry) {
            $start = $end = $portEntry;
            if (str_contains($portEntry, '-')) {
                [$start, $end] = explode('-', $portEntry);
            }

            if (!is_numeric($start) || !is_numeric($end)) {
                Notification::make()
                    ->title(trans('admin/server.notifications.invalid_port_range'))
                    ->danger()
                    ->body(trans('admin/server.notifications.invalid_port_range_body', ['port' => $portEntry]))
                    ->send();

                continue;
            }

            $start = (int) $start;
            $end = (int) $end;
            $range = $start <= $end ? range($start, $end) : range($end, $start);

            if (count($range) > $portRangeLimit) {
                Notification::make()
                    ->title(trans('admin/server.notifications.too_many_ports'))
                    ->danger()
                    ->body(trans('admin/server.notifications.too_many_ports_body', ['limit' => $portRangeLimit]))
                    ->send();

                continue;
            }

            foreach ($range as $i) {
                // Invalid port number
                if ($i <= $portFloor || $i > $portCeil) {
                    Notification::make()
                        ->title(trans('admin/server.notifications.invalid_port'))
                        ->danger()
                        ->body(trans('admin/server.notifications.invalid_port_body', ['i' => $i, 'portFloor' => $portFloor, 'portCeil' => $portCeil]))
                        ->send();

                    continue;
                }

                // Already exists
                if (in_array($i, $existingPorts)) {
                    Notification::make()
                        ->title(trans('admin/server.notifications.already_exists'))
                        ->danger()
                        ->body(trans('admin/server.notifications.already_exists_body', ['i' => $i]))
                        ->send();

                    continue;
                }

                $ports->push($i);
            }
        }

        $uniquePorts = $ports->unique()->values();
        if ($ports->count() > $uniquePorts->count()) {
            $ports = $uniquePorts;
        }

        $sortedPorts = $ports->sort()->values();
        if ($sortedPorts->all() !== $ports->all()) {
            $ports = $sortedPorts;
        }

        return $ports->all();
    }
}
