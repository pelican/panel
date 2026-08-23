<?php

namespace App\Filament\Admin\Resources\Nodes\RelationManagers;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Servers\Pages\CreateServer;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Components\Actions\UpdateNodeAllocations;
use App\Models\Allocation;
use App\Models\Node;
use App\Services\Allocations\AssignmentService;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

/**
 * @method Node getOwnerRecord()
 */
class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    protected static string|BackedEnum|null $icon = TablerIcon::Network;

    public function setTitle(): string
    {
        return trans('admin/server.allocations');
    }

    /**
     * @throws Exception
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->checkIfRecordIsSelectableUsing(fn (Allocation $allocation) => $allocation->server_id === null)
            ->paginationPageOptions([10, 20, 50, 100, 200, 500])
            ->heading(null)
            ->selectCurrentPageOnly() //Prevent people from trying to nuke 30,000 ports at once.... -,-
            ->columns([
                TextColumn::make('id')
                    ->label(trans('admin/node.table.id'))
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                SelectColumn::make('ip')
                    ->options(function (Allocation $allocation) {
                        $ips = Allocation::where('port', $allocation->port)->pluck('ip');

                        return collect($this->getOwnerRecord()->ipAddresses())
                            ->diff($ips)
                            ->unshift($allocation->ip)
                            ->unique()
                            ->mapWithKeys(fn (string $ip) => [$ip => $ip])
                            ->all();
                    })
                    ->selectablePlaceholder(false)
                    ->searchable()
                    ->sortable()
                    ->label(trans('admin/node.table.ip'))
                    ->disabled(fn () => $this->isReadOnly()),
                TextColumn::make('port')
                    ->searchable()
                    ->sortable()
                    ->label(trans('admin/node.port')),
                TextInputColumn::make('ip_alias')
                    ->searchable()
                    ->sortable()
                    ->label(trans('admin/node.table.alias'))
                    ->placeholder(trans('admin/node.table.no_alias'))
                    ->disabled(fn () => $this->isReadOnly()),
                TextInputColumn::make('notes')
                    ->label(trans('admin/node.table.allocation_notes'))
                    ->placeholder(trans('admin/node.table.no_notes'))
                    ->disabled(fn () => $this->isReadOnly()),
                TextColumn::make('server.name')
                    ->label(trans('admin/node.table.servers'))
                    ->placeholder(trans('admin/node.table.no_server'))
                    ->visibleFrom('md')
                    ->searchable()
                    ->url(fn (Allocation $allocation) => $allocation->server && user()?->can('update', $allocation->server) ? EditServer::getUrl(['record' => $allocation->server]) : null),
            ])
            ->emptyStateHeading(trans('admin/node.no_allocations'))
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (Allocation $allocation) => $allocation->server_id === null),
            ])
            ->toolbarActions([
                CreateAction::make()
                    ->createAnother(false)
                    ->icon(TablerIcon::WorldPlus)
                    ->visible(fn () => !$this->isReadOnly())
                    ->schema(fn () => [
                        Select::make('allocation_ip')
                            ->options(fn (Get $get) => collect($this->getOwnerRecord()->ipAddresses())
                                ->when($get('allocation_ip'), fn ($ips, $current) => $ips->push($current))
                                ->unique()
                                ->mapWithKeys(fn (string $ip) => [$ip => $ip]))
                            ->label(trans('admin/node.ip_address'))
                            ->inlineLabel()
                            ->ip()
                            ->helperText(trans('admin/node.ip_help'))
                            ->afterStateUpdated(fn (Set $set) => $set('allocation_ports', []))
                            ->live()
                            ->hintAction(
                                Action::make('hint_refresh')
                                    ->hiddenLabel()
                                    ->icon(TablerIcon::Refresh)
                                    ->tooltip(trans('admin/node.refresh'))
                                    ->action(function () {
                                        cache()->forget("nodes.{$this->getOwnerRecord()->id}.ips");
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
                            ->label(trans('admin/node.table.alias'))
                            ->inlineLabel()
                            ->default(null)
                            ->helperText(trans('admin/node.alias_help')),
                        TagsInput::make('allocation_ports')
                            ->placeholder('27015, 27017-27019')
                            ->label(trans('admin/node.ports'))
                            ->inlineLabel()
                            ->live()
                            ->disabled(fn (Get $get) => empty($get('allocation_ip')))
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('allocation_ports', CreateServer::retrieveValidPorts($this->getOwnerRecord(), $state, $get('allocation_ip'))))
                            ->splitKeys(['Tab', ' ', ','])
                            ->required(),
                    ])
                    ->action(fn (array $data, AssignmentService $service) => $service->handle($this->getOwnerRecord(), $data)),
                UpdateNodeAllocations::make()
                    ->nodeRecord($this->getOwnerRecord())
                    ->authorize(fn () => user()?->can('update', $this->getOwnerRecord()))
                    ->hidden(fn () => $this->isReadOnly()),
                DeleteBulkAction::make()
                    ->hidden(fn () => $this->isReadOnly()),
            ]);
    }
}
