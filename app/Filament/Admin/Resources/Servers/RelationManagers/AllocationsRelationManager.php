<?php

namespace App\Filament\Admin\Resources\Servers\RelationManagers;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Servers\Pages\CreateServer;
use App\Models\Allocation;
use App\Models\Server;
use App\Services\Allocations\AssignmentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * @method Server getOwnerRecord()
 */
class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    protected static string|BackedEnum|null $icon = TablerIcon::Network;

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->selectCurrentPageOnly()
            ->recordTitleAttribute('address')
            ->recordTitle(fn (Allocation $allocation) => $allocation->address)
            ->inverseRelationship('server')
            ->columns([
                TextColumn::make('ip')
                    ->label(trans('admin/server.ip_address'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('port')
                    ->label(trans('admin/server.port'))
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('show_port')
                    ->label(trans('admin/server.show_port')),
                TextInputColumn::make('ip_alias')
                    ->label(trans('admin/server.alias'))
                    ->placeholder(trans('admin/server.no_alias'))
                    ->searchable()
                    ->sortable()
                    ->disabled(fn () => $this->isReadOnly()),
                TextInputColumn::make('notes')
                    ->label(trans('admin/server.notes'))
                    ->placeholder(trans('admin/server.no_notes'))
                    ->disabled(fn () => $this->isReadOnly()),
                IconColumn::make('primary')
                    ->icon(fn ($state) => match ($state) {
                        true => TablerIcon::StarFilled,
                        default => TablerIcon::Star,
                    })
                    ->color(fn ($state) => match ($state) {
                        true => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (Allocation $allocation) => trans('admin/server.' . ($allocation->id === $this->getOwnerRecord()->allocation_id ? 'already' : 'make') . '_primary'))
                    ->action(function (Allocation $allocation) {
                        if ($this->isReadOnly()) {
                            return;
                        }

                        $this->getOwnerRecord()->update(['allocation_id' => $allocation->id]) && $this->deselectAllTableRecords();
                    })
                    ->default(fn (Allocation $allocation) => $allocation->id === $this->getOwnerRecord()->allocation_id)
                    ->label(trans('admin/server.primary')),
                IconColumn::make('is_locked')
                    ->label(trans('admin/server.locked'))
                    ->tooltip(trans('admin/server.locked_helper'))
                    ->trueIcon(TablerIcon::Lock)
                    ->falseIcon(TablerIcon::LockOpen),
            ])
            ->emptyStateHeading(trans('admin/server.no_allocations'))
            ->recordActions([
                Action::make('make-primary')
                    ->authorize(fn (Allocation $allocation) => user()?->can('update', $allocation))
                    ->tooltip(trans('admin/server.make_primary'))
                    ->icon(TablerIcon::Star)
                    ->action(fn (Allocation $allocation) => $this->getOwnerRecord()->update(['allocation_id' => $allocation->id]) && $this->deselectAllTableRecords())
                    ->hidden(fn (Allocation $allocation) => $this->isReadOnly() || $allocation->id === $this->getOwnerRecord()->allocation_id),
                Action::make('lock')
                    ->authorize(fn (Allocation $allocation) => user()?->can('update', $allocation))
                    ->tooltip(trans('admin/server.lock'))
                    ->icon(TablerIcon::Lock)
                    ->action(fn (Allocation $allocation) => $allocation->update(['is_locked' => true]) && $this->deselectAllTableRecords())
                    ->hidden(fn (Allocation $allocation) => $this->isReadOnly() || $allocation->is_locked),
                Action::make('unlock')
                    ->authorize(fn (Allocation $allocation) => user()?->can('update', $allocation))
                    ->tooltip(trans('admin/server.unlock'))
                    ->icon(TablerIcon::LockOpen)
                    ->action(fn (Allocation $allocation) => $allocation->update(['is_locked' => false]) && $this->deselectAllTableRecords())
                    ->visible(fn (Allocation $allocation) => !$this->isReadOnly() && $allocation->is_locked),
                DissociateAction::make()
                    ->authorize(fn (Allocation $allocation) => user()?->can('update', $allocation))
                    ->tooltip(trans('admin/server.remove_allocation'))
                    ->hidden(fn () => $this->isReadOnly())
                    ->after(function (Allocation $allocation) {
                        $allocation->update(Allocation::RELEASE_ATTRIBUTES);

                        if (!$this->getOwnerRecord()->allocation_id) {
                            $this->getOwnerRecord()->update(['allocation_id' => $this->getOwnerRecord()->allocations()->first()?->id]);
                        }
                    }),
            ])
            ->toolbarActions([
                DissociateBulkAction::make()
                    ->hidden(fn () => $this->isReadOnly())
                    ->after(function (Collection $records) {
                        Allocation::whereIn('id', $records->pluck('id'))->update(Allocation::RELEASE_ATTRIBUTES);

                        if (!$this->getOwnerRecord()->allocation_id) {
                            $this->getOwnerRecord()->update(['allocation_id' => $this->getOwnerRecord()->allocations()->first()?->id]);
                        }
                    }),
                CreateAction::make()
                    ->hidden(fn () => $this->isReadOnly())
                    ->hiddenLabel()
                    ->tooltip(trans('admin/server.create_allocation'))
                    ->icon(TablerIcon::WorldPlus)
                    ->createAnother(false)
                    ->schema(fn () => [
                        Select::make('allocation_ip')
                            ->options(fn (Get $get) => collect($this->getOwnerRecord()->node->ipAddresses())
                                ->when($get('allocation_ip'), fn ($ips, $current) => $ips->push($current))
                                ->unique()
                                ->mapWithKeys(fn (string $ip) => [$ip => $ip]))
                            ->label(trans('admin/server.ip_address'))
                            ->inlineLabel()
                            ->ip()
                            ->live()
                            ->hintAction(
                                Action::make('refresh')
                                    ->icon(TablerIcon::Refresh)
                                    ->tooltip(trans('admin/node.refresh'))
                                    ->action(function () {
                                        cache()->forget("nodes.{$this->getOwnerRecord()->node->id}.ips");
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
                            ->afterStateUpdated(fn (Set $set) => $set('allocation_ports', []))
                            ->required(),
                        TextInput::make('allocation_alias')
                            ->label(trans('admin/server.alias'))
                            ->inlineLabel()
                            ->default(null)
                            ->helperText(trans('admin/server.alias_helper')),
                        TagsInput::make('allocation_ports')
                            ->placeholder('27015, 27017-27019')
                            ->label(trans('admin/server.ports'))
                            ->inlineLabel()
                            ->live()
                            ->disabled(fn (Get $get) => empty($get('allocation_ip')))
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('allocation_ports', CreateServer::retrieveValidPorts($this->getOwnerRecord()->node, $state, $get('allocation_ip'))))
                            ->splitKeys(['Tab', ' ', ','])
                            ->required(),
                        Hidden::make('is_locked')
                            ->default(true),
                    ])
                    ->action(fn (array $data, AssignmentService $service) => $service->handle($this->getOwnerRecord()->node, $data, $this->getOwnerRecord())),
                AssociateAction::make()
                    ->hidden(fn () => $this->isReadOnly())
                    ->multiple()
                    ->associateAnother(false)
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->whereBelongsTo($this->getOwnerRecord()->node)->whereNull('server_id'))
                    ->recordSelectSearchColumns(['ip', 'port'])
                    ->tooltip(trans('admin/server.add_allocation'))
                    ->after(function (array $data) {
                        Allocation::whereIn('id', array_values(array_unique($data['recordId'])))->update(['is_locked' => true]);

                        if (!$this->getOwnerRecord()->allocation_id) {
                            $this->getOwnerRecord()->update(['allocation_id' => $data['recordId'][0]]);
                        }
                    }),
            ]);
    }
}
