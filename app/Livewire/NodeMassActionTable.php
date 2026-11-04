<?php

namespace App\Livewire;

use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use App\Enums\TablerIcon;
use App\Exceptions\Service\Server\TransferBatchException;
use App\Jobs\SendServerPowerAction;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Servers\ServerTransferBatchService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Picks the servers of a node for a mass action. Only the rows on the current page are ever
 * rendered and what was picked is kept here by server id, so the size of the node doesn't matter
 * and the selection survives searching and paging.
 *
 * @property-read Schema $form
 */
class NodeMassActionTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public const string TRANSFER = 'transfer';

    public const array SIGNALS = ['start', 'stop', 'restart'];

    #[Locked]
    public Node $node;

    #[Locked]
    public string $action;

    /** @var array<int, string> container state of each server, by server id */
    #[Locked]
    public array $states = [];

    /** @var int[] */
    #[Locked]
    public array $eligible = [];

    /** @var int[] */
    #[Locked]
    public array $selected = [];

    /** @var array<int, string> IPs that differ from the one chosen for all servers, by server id */
    #[Locked]
    public array $ipOverrides = [];

    /** @var int[] servers whose backups do the opposite of what was chosen for all servers */
    #[Locked]
    public array $backupExceptions = [];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, string>|null */
    private ?array $ipOptions = null;

    /** @var array<int, string[]>|null */
    private ?array $conflicts = null;

    /** @var array<string, int>|null */
    private ?array $targetAllocations = null;

    private ServerTransferBatchService $batchService;

    public function boot(ServerTransferBatchService $batchService): void
    {
        $this->batchService = $batchService;
    }

    public function mount(Node $node, string $action, DaemonServerRepository $repository): void
    {
        abort_unless($action === self::TRANSFER || in_array($action, self::SIGNALS, true), 404);
        abort_unless(user()?->can('update server') && user()->accessibleNodes()->whereKey($node->id)->exists(), 403);

        $this->node = $node;
        $this->action = $action;

        $states = $repository->setNode($node)->getStates();

        foreach ($node->servers()->with('transfer')->get() as $server) {
            $state = $states[$server->uuid] ?? ContainerStatus::Missing;

            $this->states[$server->id] = $state->value;

            if ($this->isEligible($server, $state)) {
                $this->eligible[] = $server->id;
            }
        }

        $this->form->fill(['all' => false, 'move_all_backups' => true]);
    }

    public function form(Schema $schema): Schema
    {
        $all = Toggle::make('all')
            ->label(trans('admin/node.mass_actions.all_servers'))
            ->disabled(empty($this->eligible))
            ->live()
            ->afterStateUpdated(function (bool $state) {
                $this->selected = $state ? $this->eligible : [];
                $this->forgetConflicts();
            });

        if (!$this->isTransfer()) {
            return $schema->statePath('data')->components([$all]);
        }

        return $schema
            ->statePath('data')
            ->components([
                Grid::make(2)->schema([
                    Select::make('node_id')
                        ->label(trans('admin/node.transfer_batch.target'))
                        ->prefixIcon(TablerIcon::Server2)
                        ->required()
                        ->live()
                        ->options(fn () => user()?->accessibleNodes()->whereNot('id', $this->node->id)->pluck('name', 'id')->all())
                        ->afterStateUpdated(function () {
                            $this->data['default_ip'] = null;
                            $this->ipOverrides = [];
                            $this->ipOptions = null;
                            $this->targetAllocations = null;
                            $this->forgetConflicts();
                        }),
                    Select::make('default_ip')
                        ->label(trans('admin/node.transfer_batch.set_all_ips'))
                        ->prefixIcon(TablerIcon::Network)
                        ->live()
                        ->disabled(fn () => !$this->targetId())
                        ->options(fn () => $this->ipOptions())
                        ->afterStateUpdated(function () {
                            $this->ipOverrides = [];
                            $this->forgetConflicts();
                        }),
                    $all,
                    Toggle::make('move_all_backups')
                        ->label(trans('admin/node.transfer_batch.move_all_backups'))
                        ->helperText(trans('admin/node.transfer_batch.move_backups_help'))
                        ->live()
                        ->afterStateUpdated(fn () => $this->backupExceptions = []),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Server::query()->where('node_id', $this->node->id)->with(['allocations', ...$this->isTransfer() ? ['backups.backupHost'] : []]))
            ->defaultSort('name')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->deferFilters(false)
            ->columns([
                ToggleColumn::make('selected')
                    ->label(trans('admin/node.mass_actions.include'))
                    ->state(fn (Server $record) => $this->isSelected($record))
                    ->disabled(fn (Server $record) => !in_array($record->id, $this->eligible, true))
                    ->updateStateUsing(function (Server $record, mixed $state) {
                        $this->select($record, (bool) $state);

                        return (bool) $state;
                    }),
                TextColumn::make('name')
                    ->label(trans('admin/node.mass_actions.server'))
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search) => $this->search($query, $search)),
                TextColumn::make('details')
                    ->label(trans('admin/node.mass_actions.details'))
                    ->color('gray')
                    ->wrap()
                    ->state(fn (Server $record) => $this->details($record)),
                SelectColumn::make('ip')
                    ->label(trans('admin/node.transfer_batch.target_ip'))
                    ->visible(fn () => $this->isTransfer())
                    ->options(fn () => $this->ipOptions())
                    ->state(fn (Server $record) => $this->ipFor($record->id))
                    ->disabled(fn (Server $record) => !$this->targetId() || !$record->allocation_id)
                    ->extraCellAttributes(fn (Server $record) => $this->onlyWhen($this->isSelected($record)))
                    ->updateStateUsing(function (Server $record, mixed $state) {
                        $this->setIp($record, $state);

                        return $state;
                    }),
                TextColumn::make('port_status')
                    ->label(trans('admin/node.transfer_batch.port_status'))
                    ->visible(fn () => $this->isTransfer())
                    ->wrap()
                    ->state(fn (Server $record) => $this->describePorts($record))
                    ->color(fn (Server $record) => isset($this->conflicts()[$record->id]) ? 'danger' : 'gray')
                    ->extraCellAttributes(fn (Server $record) => $this->onlyWhen($this->isSelected($record))),
                ToggleColumn::make('move_backups')
                    ->label(trans('admin/node.transfer_batch.move_backups_column'))
                    ->visible(fn () => $this->isTransfer())
                    ->state(fn (Server $record) => $this->movesBackups($record->id))
                    ->disabled(fn (Server $record) => !in_array($record->id, $this->eligible, true))
                    ->extraCellAttributes(fn (Server $record) => $this->onlyWhen($this->isSelected($record) && $this->batchService->localBackups($record)->isNotEmpty()))
                    ->updateStateUsing(function (Server $record, mixed $state) {
                        $this->setMovesBackups($record, (bool) $state);

                        return (bool) $state;
                    }),
            ])
            ->filters([
                Filter::make('only_selected')
                    ->label(trans('admin/node.mass_actions.filter_selected'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereKey($this->selected)),
                Filter::make('port_conflict')
                    ->label(trans('admin/node.transfer_batch.filter_conflicts'))
                    ->visible(fn () => $this->isTransfer())
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereKey(array_keys($this->conflicts()))),
            ])
            ->emptyStateHeading(trans('admin/node.mass_actions.no_servers'));
    }

    public function submit(DaemonServerRepository $repository): void
    {
        abort_unless(user()?->can('update server'), 403);

        $this->isTransfer() ? $this->submitTransfer() : $this->submitSignal($repository);
    }

    /** What is about to happen, as one line above the submit button. */
    public function summary(): string
    {
        $parts = [trans_choice('admin/node.mass_actions.selected_count', count($this->selected))];

        if ($this->isTransfer() && $conflicts = count($this->conflicts())) {
            $parts[] = trans_choice('admin/node.transfer_batch.conflict_count', $conflicts);
        }

        return implode(' · ', $parts);
    }

    public function render(): View
    {
        return view('livewire.node-mass-action-table');
    }

    private function submitSignal(DaemonServerRepository $repository): void
    {
        // States may have changed since the table was opened.
        $states = $repository->setNode($this->node)->getStates();

        $servers = $this->selectedServers()
            ->filter(fn (Server $server) => $this->isEligible($server, $states[$server->uuid] ?? ContainerStatus::Missing));

        $servers->each(fn (Server $server) => SendServerPowerAction::dispatch($server, $this->action));

        Notification::make()
            ->title(trans("admin/node.mass_actions.$this->action"))
            ->body($servers->isEmpty() ? trans('admin/node.mass_actions.none_selected') : trans_choice('admin/node.mass_actions.sent', $servers->count()))
            ->color($servers->isEmpty() ? 'warning' : 'success')
            ->send();

        $this->dispatch('mass-action-close');
    }

    private function submitTransfer(): void
    {
        $this->form->validate();

        $target = user()->accessibleNodes()->whereKey($this->targetId())->firstOrFail();

        $assignments = $this->selectedServers()
            ->mapWithKeys(fn (Server $server) => [$server->id => [
                'ip' => $this->ipFor($server->id),
                'move_backups' => $this->movesBackups($server->id),
            ]])
            ->all();

        try {
            $this->batchService->create($this->node, $target, $assignments, user());
        } catch (TransferBatchException $exception) {
            Notification::make()
                ->title(trans('admin/node.transfer_batch.notifications.not_started'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            // Takes the admin straight to what needs fixing
            if ($this->conflicts()) {
                $this->tableFilters['port_conflict']['isActive'] = true;
            }

            return;
        }

        Notification::make()
            ->title(trans_choice('admin/node.transfer_batch.notifications.started', count($assignments), ['node' => $target->name]))
            ->success()
            ->send();

        $this->dispatch('mass-action-close');
    }

    /** @return Collection<int, Server> */
    private function selectedServers(): Collection
    {
        return $this->node->servers()->whereKey(array_intersect($this->selected, $this->eligible))->with('transfer')->get();
    }

    private function isTransfer(): bool
    {
        return $this->action === self::TRANSFER;
    }

    private function isEligible(Server $server, ContainerStatus $state): bool
    {
        $isLocked = !$server->isInstalled() || $server->status === ServerState::RestoringBackup || $server->transfer !== null;

        if ($this->isTransfer()) {
            return !$isLocked;
        }

        return !$isLocked && !$server->isSuspended() && match ($this->action) {
            'start' => $state === ContainerStatus::Offline,
            'stop' => $state->isStartingOrRunning(),
            'restart' => $state === ContainerStatus::Running,
            default => false,
        };
    }

    private function isSelected(Server $server): bool
    {
        return in_array($server->id, $this->selected, true);
    }

    private function select(Server $server, bool $selected): void
    {
        abort_unless(in_array($server->id, $this->eligible, true), 403);

        $this->selected = $selected
            ? array_values(array_unique([...$this->selected, $server->id]))
            : array_values(array_diff($this->selected, [$server->id]));

        $this->data['all'] = count($this->selected) === count($this->eligible);
        $this->forgetConflicts();
    }

    private function setIp(Server $server, mixed $ip): void
    {
        if (filled($ip)) {
            $this->ipOverrides[$server->id] = (string) $ip;
        } else {
            unset($this->ipOverrides[$server->id]);
        }

        $this->forgetConflicts();
    }

    private function ipFor(int $serverId): ?string
    {
        return $this->ipOverrides[$serverId] ?? $this->data['default_ip'] ?? null;
    }

    private function setMovesBackups(Server $server, bool $moves): void
    {
        $this->backupExceptions = $moves === (bool) ($this->data['move_all_backups'] ?? true)
            ? array_values(array_diff($this->backupExceptions, [$server->id]))
            : array_values(array_unique([...$this->backupExceptions, $server->id]));
    }

    private function movesBackups(int $serverId): bool
    {
        return (bool) ($this->data['move_all_backups'] ?? true) !== in_array($serverId, $this->backupExceptions, true);
    }

    private function targetId(): ?int
    {
        return filled($this->data['node_id'] ?? null) ? (int) $this->data['node_id'] : null;
    }

    private function search(Builder $query, string $search): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('name', 'like', "%$search%")
            ->orWhereHas('allocations', fn (Builder $query) => $query
                ->where('ip', 'like', "%$search%")
                ->when(ctype_digit($search), fn (Builder $query) => $query->orWhere('port', (int) $search))));
    }

    private function details(Server $server): string
    {
        $state = ContainerStatus::tryFrom($this->states[$server->id] ?? '') ?? ContainerStatus::Missing;
        $backups = $this->isTransfer() ? $this->batchService->localBackups($server)->count() : 0;

        return collect([
            $state->getLabel(),
            ...$server->allocations->where('node_id', $this->node->id)->map(fn (Allocation $allocation) => "$allocation->ip:$allocation->port"),
            ...$backups > 0 ? [trans_choice('admin/node.transfer_batch.local_backups', $backups)] : [],
        ])->implode(' · ');
    }

    /** @return array<string, string> */
    private function ipOptions(): array
    {
        if (!$targetId = $this->targetId()) {
            return [];
        }

        if ($this->ipOptions === null) {
            $target = Node::find($targetId);
            $this->ipOptions = $target ? collect($this->batchService->availableIps($target))->mapWithKeys(fn (string $ip) => [$ip => $ip])->all() : [];
        }

        return $this->ipOptions;
    }

    private function describePorts(Server $server): ?string
    {
        $ip = $this->ipFor($server->id);

        if (!$this->targetId() || !$ip) {
            return null;
        }

        // server_id of each allocation on the target, 0 when it is free
        $this->targetAllocations ??= Allocation::query()->where('node_id', $this->targetId())->get()
            ->mapWithKeys(fn (Allocation $allocation) => ["$allocation->ip:$allocation->port" => $allocation->server_id ?? 0])
            ->all();

        return $server->allocations->where('node_id', $this->node->id)->map(function (Allocation $allocation) use ($ip) {
            $key = "$ip:$allocation->port";

            $status = match ($this->targetAllocations[$key] ?? null) {
                null => 'port_create',
                0 => 'port_free',
                default => 'port_taken',
            };

            return "$key (" . trans("admin/node.transfer_batch.$status") . ')';
        })->implode(', ');
    }

    /** @return array<int, string[]> */
    private function conflicts(): array
    {
        if ($this->conflicts !== null) {
            return $this->conflicts;
        }

        $target = $this->targetId() ? Node::find($this->targetId()) : null;

        if (!$target || !$this->selected) {
            return $this->conflicts = [];
        }

        $servers = Server::query()->whereKey($this->selected)->with('allocations')->get();
        $ips = $servers->mapWithKeys(fn (Server $server) => [$server->id => $this->ipFor($server->id)])->all();

        return $this->conflicts = $this->batchService->portConflicts($target, $servers, $ips);
    }

    private function forgetConflicts(): void
    {
        $this->conflicts = null;
    }

    /**
     * Keeps a cell in place, so the columns stay put, but out of sight.
     *
     * @return array<string, string>
     */
    private function onlyWhen(bool $condition): array
    {
        return $condition ? [] : ['style' => 'visibility: hidden'];
    }
}
