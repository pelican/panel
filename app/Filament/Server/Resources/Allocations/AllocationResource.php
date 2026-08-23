<?php

namespace App\Filament\Server\Resources\Allocations;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Server\Resources\Allocations\Pages\ListAllocations;
use App\Models\Allocation;
use App\Models\Server;
use App\Services\Allocations\FindAssignableAllocationService;
use App\Traits\Filament\BlockAccessInConflict;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyTable;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class AllocationResource extends Resource
{
    use BlockAccessInConflict;
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyTable;

    protected static ?string $model = Allocation::class;

    protected static ?int $navigationSort = 7;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Network;

    /**
     * @throws Exception
     */
    public static function defaultTable(Table $table): Table
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $table
            ->columns([
                TextColumn::make('ip')
                    ->label(trans('server/network.address'))
                    ->formatStateUsing(fn (Allocation $allocation) => $allocation->alias),
                TextColumn::make('alias')
                    ->hidden(),
                TextColumn::make('port')
                    ->label(trans('server/network.port'))
                    ->state(fn (Allocation $allocation) => $allocation->show_port ? $allocation->port : null)
                    ->placeholder(trans('server/network.port_hidden')),
                TextInputColumn::make('notes')
                    ->label(trans('server/network.notes'))
                    ->visibleFrom('sm')
                    ->disabled(fn () => !user()?->can(SubuserPermission::AllocationUpdate, $server))
                    ->placeholder(trans('server/network.no_notes')),
                IconColumn::make('primary')
                    ->icon(fn ($state) => match ($state) {
                        true => TablerIcon::StarFilled,
                        default => TablerIcon::Star,
                    })
                    ->color(fn ($state) => match ($state) {
                        true => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (Allocation $allocation) => $allocation->id === $server->allocation_id ? trans('server/network.primary') : trans('server/network.make_primary'))
                    ->action(fn (Allocation $allocation) => user()?->can(SubuserPermission::AllocationUpdate, $server) && $server->update(['allocation_id' => $allocation->id]))
                    ->default(fn (Allocation $allocation) => $allocation->id === $server->allocation_id)
                    ->label(trans('server/network.primary')),
                IconColumn::make('is_locked')
                    ->label(trans('server/network.locked'))
                    ->tooltip(trans('server/network.locked_helper'))
                    ->trueIcon(TablerIcon::Lock)
                    ->falseIcon(TablerIcon::LockOpen),
            ])
            ->recordActions([
                DetachAction::make()
                    ->visible(fn (Allocation $allocation) => !$allocation->is_locked || user()?->can('update', $allocation->node))
                    ->authorize(fn () => user()?->can(SubuserPermission::AllocationDelete, $server))
                    ->label(trans('server/network.delete'))
                    ->action(function (Allocation $allocation) {
                        Allocation::where('id', $allocation->id)->update([
                            'notes' => null,
                            'is_locked' => false,
                            'show_port' => true,
                            'server_id' => null,
                        ]);

                        Activity::event('server:allocation.delete')
                            ->subject($allocation)
                            ->property('allocation', $allocation->address)
                            ->log();
                    })
                    ->after(fn (Allocation $allocation) => $allocation->id === $server->allocation_id && $server->update(['allocation_id' => $server->allocations()->first()?->id])),
            ])
            ->toolbarActions([
                Action::make('add_allocation')
                    ->hiddenLabel()
                    ->icon(fn () => $server->allocations()->count() >= $server->allocation_limit ? TablerIcon::NetworkOff : TablerIcon::Network)
                    ->authorize(fn () => user()?->can(SubuserPermission::AllocationCreate, $server))
                    ->tooltip(fn () => $server->allocations()->count() >= $server->allocation_limit ? trans('server/network.limit') : trans('server/network.add'))
                    ->hidden(fn () => !config('panel.client_features.allocations.enabled') || $server->allocation === null)
                    ->disabled(fn () => $server->allocations()->count() >= $server->allocation_limit)
                    ->color(fn () => $server->allocations()->count() >= $server->allocation_limit ? 'danger' : 'primary')
                    ->action(function (FindAssignableAllocationService $service) use ($server) {
                        $allocation = $service->handle($server);

                        if (!$server->allocation_id) {
                            $server->update(['allocation_id' => $allocation->id]);
                        }

                        Activity::event('server:allocation.create')
                            ->subject($allocation)
                            ->property('allocation', $allocation->address)
                            ->log();
                    }),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListAllocations::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return trans('server/network.title');
    }
}
