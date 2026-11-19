<?php

namespace App\Filament\Admin\Resources\Servers\Pages;

use App\Enums\StartupVariableType;
use App\Enums\TablerIcon;
use App\Exceptions\DisplayException;
use App\Filament\Admin\Resources\Servers\ServerResource;
use App\Filament\Components\Forms\Fields\StartupVariable;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Allocations\AssignmentService;
use App\Services\Backups\BackupConfigResolver;
use App\Services\Backups\RestoreSource;
use App\Services\Backups\ServerConfigImporterService;
use App\Services\Backups\ServerRestoreService;
use App\Services\Helpers\EggHubService;
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
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
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

    private ServerConfigImporterService $configImporter;

    private BackupConfigResolver $configResolver;

    private ServerRestoreService $restoreService;

    private EggHubService $eggHubService;

    /**
     * Where the files come from when creating a server from a backup.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $restoreSource = null;

    #[Locked]
    public ?string $restoreKey = null;

    /**
     * The .pelicanconfig of the backup being restored, if it has one.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $restoreConfig = null;

    /**
     * The backup's addresses that could not be selected on the chosen node.
     *
     * @var string[]
     */
    #[Locked]
    public array $restoreMissingAllocations = [];

    /**
     * The restored egg's variables the backup has no value for.
     *
     * @var list<array{name: string, env_variable: string, required_empty: bool}>
     */
    #[Locked]
    public array $restoreDefaultedVariables = [];

    /** The egg the backup's variables belong to. */
    #[Locked]
    public ?int $restoreEggId = null;

    public function boot(
        ServerCreationService $serverCreationService,
        ServerConfigImporterService $configImporter,
        BackupConfigResolver $configResolver,
        ServerRestoreService $restoreService,
        EggHubService $eggHubService,
    ): void {
        $this->serverCreationService = $serverCreationService;
        $this->configImporter = $configImporter;
        $this->configResolver = $configResolver;
        $this->restoreService = $restoreService;
        $this->eggHubService = $eggHubService;
    }

    public function mount(): void
    {
        $key = request()->query('restore');
        // Nothing is read from a source the administrator may not use.
        if (is_string($key) && is_array($source = session()->get("restore_source.$key")) && RestoreSource::fromArray($source)->isAccessibleBy(user())) {
            $this->restoreSource = $source;
            $this->restoreKey = $key;
        }

        parent::mount();
    }

    protected function fillForm(): void
    {
        parent::fillForm();

        if (!$this->restoreSource) {
            return;
        }

        $importer = $this->configImporter;

        try {
            $config = $this->configResolver->resolve(RestoreSource::fromArray($this->restoreSource));
            $this->restoreConfig = $config ? $importer->parse($config) : null;
        } catch (DisplayException $exception) {
            Notification::make()
                ->warning()
                ->title(trans('admin/backup.restore.config_unusable'))
                ->body($exception->getMessage())
                ->send();
        }

        if (!$this->restoreConfig) {
            Notification::make()
                ->warning()
                ->title(trans('admin/backup.restore.no_config'))
                ->body(trans('admin/backup.restore.no_config_body'))
                ->persistent()
                ->send();

            $this->form->fill(array_merge($this->form->getRawState(), ['skip_scripts' => true]));

            return;
        }

        $state = array_merge($this->form->getRawState(), $importer->toFormState($this->restoreConfig));

        if (!$importer->findEgg($this->restoreConfig)) {
            $this->sendEggMissingNotification();
        }

        $this->restoreEggId = $state['egg_id'] ?? null;
        $this->restoreDefaultedVariables = $importer->defaultedVariables($this->restoreConfig);

        $allocations = $importer->matchAllocations($this->restoreConfig, Node::find($state['node_id'] ?? null));
        $state['allocation_id'] = $allocations['allocation_id'];
        $this->restoreMissingAllocations = $allocations['missing'];

        $this->form->fill(Arr::except($state, ['allocation_additional', 'server_variables']));

        // Filling the form reloads the variables from the (not yet existing) server and
        // does not wrap simple repeater items, so both repeaters are set afterwards in
        // the shape their items have.
        $this->data['allocation_additional'] = $this->repeaterItems(array_map(fn (int $id) => ['extra_allocations' => $id], $allocations['allocation_additional']));
        $this->setRestoredServerVariables($state['server_variables'] ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $variables
     */
    private function setRestoredServerVariables(array $variables): void
    {
        $this->data['server_variables'] = $this->repeaterItems(array_map(
            // Set this way the field's state casts do not run, and a toggle would show "0"
            // as on. Toggles hold 0 or 1, like StartupVariable's boolean cast makes them.
            fn (array $variable) => StartupVariable::typeForRules($variable['rules'] ?? []) === StartupVariableType::Toggle
                ? [...$variable, 'variable_value' => (int) boolval($variable['variable_value'])]
                : $variable,
            $variables,
        ));
    }

    /**
     * Installing an egg from the Hub is an egg import, like ImportEggAction.
     */
    private function canInstallEggs(): bool
    {
        return (bool) user()?->can('import egg');
    }

    /**
     * Tells the administrator the backup's egg is missing. When the Hub has the
     * egg and they may import eggs, they can install it from there.
     */
    private function sendEggMissingNotification(): void
    {
        $uuid = (string) data_get($this->restoreConfig, 'egg.uuid', '');
        $hubEgg = $uuid !== '' && $this->canInstallEggs() ? $this->eggHubService->findByUuid($uuid) : null;

        Notification::make()
            ->warning()
            ->title(trans('admin/backup.restore.egg_missing', ['egg' => data_get($this->restoreConfig, 'egg.name', '?')]))
            ->body($hubEgg ? trans('admin/backup.restore.egg_missing_hub_body', ['egg' => $hubEgg['name']]) : trans('admin/backup.restore.egg_missing_body'))
            ->actions($hubEgg ? [
                Action::make('install_egg_from_hub')
                    ->label(trans('admin/backup.restore.install_from_hub'))
                    ->button()
                    ->dispatch('install-egg-from-hub')
                    ->close(),
            ] : [])
            ->persistent()
            ->send();
    }

    /**
     * Installs the backup's egg from the Hub, then fills in the egg settings the
     * backup has for it.
     */
    #[On('install-egg-from-hub')]
    public function installEggFromHub(): void
    {
        $uuid = (string) data_get($this->restoreConfig, 'egg.uuid', '');

        if (!$this->canInstallEggs() || $uuid === '') {
            return;
        }

        try {
            $egg = $this->configImporter->findEgg($this->restoreConfig) ?? $this->eggHubService->install($uuid);
        } catch (Exception $exception) {
            Notification::make()
                ->danger()
                ->title(trans('admin/backup.restore.install_from_hub_failed'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        // Only the egg's settings are filled in, so other changes in the wizard are kept.
        $state = Arr::only($this->configImporter->toFormState($this->restoreConfig), ['egg_id', 'startup', 'select_startup', 'image', 'select_image', 'environment', 'server_variables']);
        // Filling the form does not keep the repeaters' items as they are, see fillForm().
        $additionalAllocations = $this->data['allocation_additional'] ?? [];
        $this->form->fill(array_merge($this->form->getRawState(), Arr::except($state, ['server_variables'])));
        $this->data['allocation_additional'] = $additionalAllocations;
        $this->setRestoredServerVariables($state['server_variables'] ?? []);

        $this->restoreEggId = $egg->id;
        $this->restoreDefaultedVariables = $this->configImporter->defaultedVariables($this->restoreConfig);

        Notification::make()
            ->success()
            ->title(trans('admin/backup.restore.installed_from_hub', ['egg' => $egg->name]))
            ->send();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function repeaterItems(array $items): array
    {
        return collect($items)->mapWithKeys(fn (array $item) => [(string) Str::uuid() => $item])->all();
    }

    /**
     * Selects the backup's allocations on the given node.
     */
    public function applyRestoreAllocations(Set $set, ?Node $node): void
    {
        if (!$this->restoreConfig) {
            return;
        }

        $allocations = $this->configImporter->matchAllocations($this->restoreConfig, $node);
        $set('allocation_id', $allocations['allocation_id']);
        $set('allocation_additional', $this->repeaterItems(array_map(fn (int $id) => ['extra_allocations' => $id], $allocations['allocation_additional'])));
        $this->restoreMissingAllocations = $allocations['missing'];
    }

    /**
     * Shows what is being restored, and which of the backup's addresses are
     * not available on the chosen node.
     */
    protected function getRestoreSection(): Section
    {
        return Section::make(trans('admin/backup.restore.banner_title'))
            ->icon(TablerIcon::Restore)
            ->iconColor('primary')
            ->visible(fn () => $this->restoreSource !== null)
            ->description(fn () => $this->restoreSource ? trans('admin/backup.restore.banner_body', ['source' => RestoreSource::fromArray($this->restoreSource)->label()]) : null)
            ->columnSpanFull()
            ->schema([
                TextEntry::make('restore_missing_allocations')
                    ->label(trans('admin/backup.restore.missing_allocations'))
                    ->state(fn () => implode(', ', $this->restoreMissingAllocations))
                    ->visible(fn () => !empty($this->restoreMissingAllocations))
                    ->helperText(trans('admin/backup.restore.missing_allocations_help')),
                // A labeled button right below the addresses it creates.
                Actions::make([
                    Action::make('create_missing_allocations')
                        ->label(trans('admin/backup.restore.create_allocations'))
                        ->button()
                        ->size(Size::Small)
                        ->icon(TablerIcon::Plus)
                        ->authorize(fn () => user()?->can('create', Allocation::class))
                        // The node comes from the form state, so it is looked up among the nodes the
                        // admin can access, like the node select offers.
                        ->visible(fn (Get $get) => !empty($this->restoreMissingAllocations) && ($node = user()?->accessibleNodes()->find($get('node_id'))) &&
                            !empty($this->configImporter->creatableAllocations($this->restoreMissingAllocations, $node)))
                        ->action(function (Get $get, Set $set, AssignmentService $assignmentService) {
                            $node = user()?->accessibleNodes()->findOrFail($get('node_id'));
                            $creatable = $this->configImporter->creatableAllocations($this->restoreMissingAllocations, $node);

                            foreach ($creatable as $ip => $ports) {
                                $assignmentService->handle($node, [
                                    'allocation_ip' => $ip,
                                    'allocation_ports' => array_map('strval', $ports),
                                ]);
                            }

                            $this->applyRestoreAllocations($set, $node);
                        }),
                ])->key('restore_allocation_actions'),
                // Only shown while the backup's egg is selected, another egg has other variables.
                TextEntry::make('restore_defaulted_variables')
                    ->label(trans('admin/backup.restore.defaulted_variables'))
                    ->state(fn () => collect($this->restoreDefaultedVariables)->map(fn (array $variable) => "{$variable['name']} ({$variable['env_variable']})")->implode(', '))
                    ->visible(fn (Get $get) => !empty($this->restoreDefaultedVariables) && (int) $get('egg_id') === $this->restoreEggId)
                    ->helperText(trans('admin/backup.restore.defaulted_variables_help')),
                TextEntry::make('restore_required_variables')
                    ->label(trans('admin/backup.restore.required_variables'))
                    ->state(fn () => collect($this->restoreDefaultedVariables)->where('required_empty', true)->map(fn (array $variable) => "{$variable['name']} ({$variable['env_variable']})")->implode(', '))
                    ->color('danger')
                    ->visible(fn (Get $get) => collect($this->restoreDefaultedVariables)->contains('required_empty', true) && (int) $get('egg_id') === $this->restoreEggId)
                    ->helperText(trans('admin/backup.restore.required_variables_help')),
            ]);
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
                    $this->getRestoreSection(),

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
                            // A server created from a backup starts on the backup's node, where its
                            // allocations are.
                            $backupNode = $this->restoreSource ? RestoreSource::fromArray($this->restoreSource)->getBackup()?->sourceNode() : null;
                            if ($backupNode && user()?->accessibleNodes()->whereKey($backupNode->id)->exists()) {
                                $this->node = $backupNode;

                                return $this->node->id;
                            }

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
                            $this->applyRestoreAllocations($set, $this->node);
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
                            // The node comes from the form state, so it is looked up among the nodes
                            // the admin can access, like the node select offers.
                            return collect(
                                $assignmentService->handle(user()?->accessibleNodes()->findOrFail($get('node_id')), $data)
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
                        ->disabled(fn () => $this->restoreSource !== null)
                        ->dehydrated()
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
                                        ->required()
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
                                        ->required()
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
                                        ->required()
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
                                        ->required()
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
                                        ->required()
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
                                        ->required()
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

        // The backup is restored once the server is installed, and the server is
        // only started after that.
        $startOnCompletion = (bool) ($data['start_on_completion'] ?? false);
        if ($this->restoreSource) {
            $data['skip_scripts'] = true;
            $data['start_on_completion'] = false;
        }

        try {
            if (!$this->restoreSource) {
                return $this->serverCreationService->handle($data);
            }

            // The restore is recorded before the node is asked to create the server, so a
            // fast (skipped) install that reports back right away still finds it.
            $source = RestoreSource::fromArray($this->restoreSource);
            if (!$source->isAccessibleBy(user())) {
                throw new DisplayException(trans('admin/backup.restore.source_not_accessible'));
            }
            // The node downloads the link, so it is checked again in case its host now
            // resolves to another address than when it was entered.
            if ($source->type === RestoreSource::TYPE_URL && !$this->configResolver->isPublicUrl((string) $source->url)) {
                throw new DisplayException(trans('admin/backup.restore.url_not_public'));
            }

            $server = $this->serverCreationService->handle($data, beforeDaemon: function (Server $server) use ($source, $startOnCompletion) {
                $this->restoreService->create($server, $source, $startOnCompletion);
            });

            if ($this->restoreKey) {
                session()->forget("restore_source.$this->restoreKey");
            }

            return $server;
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
     * A server created from a backup opens on its edit page, where the restore
     * can be followed and the server managed once its files are back.
     */
    protected function getRedirectUrl(): string
    {
        if ($this->restoreSource && $this->getResource()::canEdit($this->getRecord())) {
            return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
        }

        return parent::getRedirectUrl();
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
                if ($i < $portFloor || $i > $portCeil) {
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
