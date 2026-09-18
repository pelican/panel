<?php

namespace App\Filament\App\Resources\Servers\Pages;

use App\Enums\CustomizationKey;
use App\Enums\ServerResourceType;
use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Filament\App\Resources\Servers\ServerResource;
use App\Filament\Components\Tables\Columns\ProgressBarColumn;
use App\Filament\Components\Tables\Columns\ServerEntryColumn;
use App\Filament\Server\Pages\Console;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Attributes\On;

class ListServers extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    public const WARNING_THRESHOLD = 0.7;

    public const DANGER_THRESHOLD = 0.9;

    protected static string $resource = ServerResource::class;

    private DaemonServerRepository $daemonServerRepository;

    public function boot(): void
    {
        $this->daemonServerRepository = new DaemonServerRepository();
    }

    /** @return Stack[]
     */
    protected function gridColumns(): array
    {
        return [
            Stack::make([
                ServerEntryColumn::make('server_entry')
                    ->warningThresholdPercent(static::WARNING_THRESHOLD)
                    ->dangerThresholdPercent(static::DANGER_THRESHOLD)
                    ->searchable(['name']),
            ]),
        ];
    }

    /** @return Column[] */
    protected function tableColumns(): array
    {
        return [
            ImageColumn::make('icon')
                ->label('')
                ->imageSize(46)
                ->state(fn (Server $server) => $server->icon ?: $server->egg->icon),
            TextColumn::make('condition')
                ->label(trans('server/dashboard.status'))
                ->badge()
                ->tooltip(fn (Server $server) => $server->formatResource(ServerResourceType::Uptime, 2))
                ->icon(fn (Server $server) => $server->condition->getIcon())
                ->color(fn (Server $server) => $server->condition->getColor()),
            TextColumn::make('name')
                ->label(trans('server/dashboard.title'))
                ->description(fn (Server $server) => $server->description)
                ->grow()
                ->searchable()
                ->sortable(),
            TextColumn::make('allocation.display_address')
                ->label('')
                ->badge()
                ->visibleFrom('md')
                ->copyable()
                ->state(fn (Server $server) => $server->allocation->display_address ?? 'None'),
            ProgressBarColumn::make('cpuUsage')
                ->label('')
                ->warningThresholdPercent(static::WARNING_THRESHOLD)
                ->dangerThresholdPercent(static::DANGER_THRESHOLD)
                ->maxValue(fn (Server $server) => ServerResourceType::CPULimit->getResourceAmount($server) === 0 ? (($server->node->systemInformation()['cpu_count'] ?? 0) * 100) : ServerResourceType::CPULimit->getResourceAmount($server))
                ->state(fn (Server $server) => $server->retrieveResources()['cpu_absolute'] ?? 0)
                ->helperLabel(fn (Server $server) => $server->formatResource(ServerResourceType::CPU, 0) . ' / ' . $server->formatResource(ServerResourceType::CPULimit, 0)),
            ProgressBarColumn::make('memoryUsage')
                ->label('')
                ->warningThresholdPercent(static::WARNING_THRESHOLD)
                ->dangerThresholdPercent(static::DANGER_THRESHOLD)
                ->maxValue(fn (Server $server) => ServerResourceType::MemoryLimit->getResourceAmount($server) === 0 ? $server->node->statistics()['memory_total'] : ServerResourceType::MemoryLimit->getResourceAmount($server))
                ->state(fn (Server $server) => $server->retrieveResources()['memory_bytes'] ?? 0)
                ->helperLabel(fn (Server $server) => $server->formatResource(ServerResourceType::Memory) . ' / ' . $server->formatResource(ServerResourceType::MemoryLimit)),
            ProgressBarColumn::make('diskUsage')
                ->label('')
                ->warningThresholdPercent(static::WARNING_THRESHOLD)
                ->dangerThresholdPercent(static::DANGER_THRESHOLD)
                ->maxValue(fn (Server $server) => ServerResourceType::DiskLimit->getResourceAmount($server) === 0 ? $server->node->statistics()['disk_total'] : ServerResourceType::DiskLimit->getResourceAmount($server))
                ->state(fn (Server $server) => $server->retrieveResources()['disk_bytes'] ?? 0)
                ->helperLabel(fn (Server $server) => $server->formatResource(ServerResourceType::Disk) . ' / ' . $server->formatResource(ServerResourceType::DiskLimit)),
        ];
    }

    protected function usingGrid(): bool
    {
        return user()?->getCustomization(CustomizationKey::DashboardLayout) === 'grid';
    }

    public function getTablePerPageSessionKey(): string
    {
        // Grid and list offer different page options, so a session value cached in
        // one layout must not bleed into (and get invalidated by) the other.
        return parent::getTablePerPageSessionKey() . ($this->usingGrid() ? '_grid' : '_list');
    }

    public function table(Table $table): Table
    {
        $baseQuery = user()?->accessibleServers();

        $usingGrid = $this->usingGrid();

        $pageOptions = $usingGrid ? [10, 20, 30, 40] : [10, 20, 50, 100];
        $storedPerPage = (int) user()?->getCustomization(CustomizationKey::ServersPerPage);
        $defaultPerPage = in_array($storedPerPage, $pageOptions, true) ? $storedPerPage : ($usingGrid ? 10 : 20);

        return $table
            ->deferLoading(!$usingGrid)
            ->paginated($pageOptions)
            ->defaultPaginationPageOption($defaultPerPage)
            ->query(fn () => $baseQuery)
            ->poll($usingGrid ? null : '15s')
            ->columns($usingGrid ? $this->gridColumns() : $this->tableColumns())
            ->recordUrl(!$usingGrid ? (fn (Server $server) => Console::getUrl(panel: 'server', tenant: $server)) : null)
            ->recordActions(!$usingGrid ? static::getPowerActionGroup() : [])
            ->recordActionsAlignment(Alignment::Center->value)
            ->contentGrid($usingGrid ? ['default' => 1, 'md' => 2] : null)
            ->emptyStateIcon(TablerIcon::BrandDocker)
            ->emptyStateDescription('')
            ->emptyStateHeading(fn () => $this->activeTab === 'my' ? trans('server/dashboard.empty_own') : trans('server/dashboard.empty_other'))
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('egg')
                    ->relationship('egg', 'name', fn (Builder $query) => $query->whereIn('id', $baseQuery->pluck('egg_id')))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('owner')
                    ->relationship('user', 'username', fn (Builder $query) => $query->whereIn('id', $baseQuery->pluck('owner_id')))
                    ->searchable()
                    ->hidden(fn () => $this->activeTab === 'my')
                    ->preload(),
            ]);
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    public function updatedTableRecordsPerPage(): void
    {
        parent::updatedTableRecordsPerPage();

        // Livewire delivers the <select> value as a numeric string, and the
        // client can send anything, so only persist a currently offered option.
        $perPage = (int) $this->getTableRecordsPerPage();

        if (!in_array($perPage, $this->getTable()->getPaginationPageOptions(), true)) {
            return;
        }

        user()?->setCustomization(CustomizationKey::ServersPerPage, $perPage);
    }

    public function getTabs(): array
    {
        $all = user()?->accessibleServers();
        $my = (clone $all)->where('owner_id', user()?->id);
        $other = (clone $all)->whereNot('owner_id', user()?->id);

        return [
            'my' => Tab::make('my')
                ->label(trans('server/dashboard.tabs.my'))
                ->badge(fn () => $my->count())
                ->modifyQueryUsing(fn () => $my),

            'other' => Tab::make('other')
                ->label(trans('server/dashboard.tabs.other'))
                ->badge(fn () => $other->count())
                ->modifyQueryUsing(fn () => $other),

            'all' => Tab::make('all')
                ->label(trans('server/dashboard.tabs.all'))
                ->badge($all->count()),
        ];
    }

    protected function getResourceColor(Server $server, string $resource): ?string
    {
        $current = null;
        $limit = null;

        switch ($resource) {
            case 'cpu':
                $current = $server->retrieveResources()['cpu_absolute'] ?? 0;
                $limit = $server->cpu;
                if ($server->cpu === 0) {
                    return null;
                }
                break;
            case 'memory':
                $current = $server->retrieveResources()['memory_bytes'] ?? 0;
                $limit = $server->memory * 2 ** 20;
                if ($server->memory === 0) {
                    return null;
                }
                break;
            case 'disk':
                $current = $server->retrieveResources()['disk_bytes'] ?? 0;
                $limit = $server->disk * 2 ** 20;
                if ($server->disk === 0) {
                    return null;
                }
                break;
            default:
                return null;
        }

        if ($current >= $limit * self::DANGER_THRESHOLD) {
            return 'danger';
        }

        if ($current >= $limit * self::WARNING_THRESHOLD) {
            return 'warning';
        }

        return null;
    }

    #[On('powerAction')]
    public function powerAction(Server $server, string $action): void
    {
        try {
            $this->daemonServerRepository->setServer($server)->power($action);

            Notification::make()
                ->title(trans('server/dashboard.power_actions'))
                ->body(trans('server/dashboard.power_action_sent', ['action' => $action, 'name' => $server->name]))
                ->success()
                ->send();

            cache()->forget("servers.$server->uuid.status");

            $this->redirect(self::getUrl(['tab' => $this->activeTab]));
        } catch (ConnectionException) {
            Notification::make()
                ->title(trans('exceptions.node.error_connecting', ['node' => $server->node->name]))
                ->danger()
                ->send();
        }
    }

    public static function getPowerActionGroup(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('start')
                ->label(trans('server/console.power_actions.start'))
                ->color('primary')
                ->icon(TablerIcon::PlayerPlayFilled)
                ->authorize(fn (Server $server) => user()?->can(SubuserPermission::ControlStart, $server))
                ->visible(fn (Server $server) => $server->retrieveStatus()->isStartable())
                ->dispatch('powerAction', fn (Server $server) => ['server' => $server, 'action' => 'start']),
            Action::make('restart')
                ->label(trans('server/console.power_actions.restart'))
                ->color('gray')
                ->icon(TablerIcon::Reload)
                ->authorize(fn (Server $server) => user()?->can(SubuserPermission::ControlRestart, $server))
                ->visible(fn (Server $server) => $server->retrieveStatus()->isRestartable())
                ->dispatch('powerAction', fn (Server $server) => ['server' => $server, 'action' => 'restart']),
            Action::make('stop')
                ->label(trans('server/console.power_actions.stop'))
                ->color('danger')
                ->icon(TablerIcon::PlayerStopFilled)
                ->authorize(fn (Server $server) => user()?->can(SubuserPermission::ControlStop, $server))
                ->visible(fn (Server $server) => $server->retrieveStatus()->isStoppable() && !$server->retrieveStatus()->isKillable())
                ->dispatch('powerAction', fn (Server $server) => ['server' => $server, 'action' => 'stop']),
            Action::make('kill')
                ->label(trans('server/console.power_actions.kill'))
                ->color('danger')
                ->icon(TablerIcon::AlertSquare)
                ->tooltip(trans('server/console.power_actions.kill_tooltip'))
                ->authorize(fn (Server $server) => user()?->can(SubuserPermission::ControlStop, $server))
                ->visible(fn (Server $server) => $server->retrieveStatus()->isKillable())
                ->dispatch('powerAction', fn (Server $server) => ['server' => $server, 'action' => 'kill']),
        ])
            ->icon(TablerIcon::Power)
            ->color('primary')
            ->tooltip(trans('server/dashboard.power_actions'))
            ->hidden(fn (Server $server) => $server->isInConflictState())
            ->iconSize(IconSize::Large);
    }
}
