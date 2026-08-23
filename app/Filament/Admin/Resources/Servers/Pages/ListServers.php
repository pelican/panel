<?php

namespace App\Filament\Admin\Resources\Servers\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Eggs\Pages\EditEgg;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Servers\ServerResource;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Components\Actions\ViewConsoleAction;
use App\Models\Server;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListServers extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = ServerResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->defaultGroup('node.name')
            ->groups([
                Group::make('node.name')->getDescriptionFromRecordUsing(fn (Server $server) => str($server->node->description)->limit(150)),
                Group::make('user.username')->getDescriptionFromRecordUsing(fn (Server $server) => $server->user->email),
                Group::make('egg.name')->getDescriptionFromRecordUsing(fn (Server $server) => str($server->egg->description)->limit(150)),
            ])
            ->columns([
                TextColumn::make('condition')
                    ->label(trans('admin/server.condition'))
                    ->default('unknown')
                    ->badge()
                    ->icon(fn (Server $server) => $server->condition->getIcon())
                    ->color(fn (Server $server) => $server->condition->getColor()),
                TextColumn::make('uuid')
                    ->hidden()
                    ->label(trans('admin/server.uuid'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(trans('admin/server.name'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        Server::query()->qualifyColumn('name'), 'like', "%{$search}%")
                    )
                    ->sortable(),
                TextColumn::make('node.name')
                    ->label(trans('admin/server.node'))
                    ->url(fn (Server $server) => user()?->can('update', $server->node) ? EditNode::getUrl(['record' => $server->node]) : null)
                    ->hidden(fn (Table $table) => $table->getGrouping()?->getId() === 'node.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('egg.name')
                    ->label(trans('admin/server.egg'))
                    ->url(fn (Server $server) => user()?->can('update', $server->egg) ? EditEgg::getUrl(['record' => $server->egg]) : null)
                    ->hidden(fn (Table $table) => $table->getGrouping()?->getId() === 'egg.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.username')
                    ->label(trans('admin/user.username'))
                    ->url(fn (Server $server) => user()?->can('update', $server->user) ? EditUser::getUrl(['record' => $server->user]) : null)
                    ->hidden(fn (Table $table) => $table->getGrouping()?->getId() === 'user.username')
                    ->sortable()
                    ->searchable(),
                SelectColumn::make('allocation_id')
                    ->label(trans('admin/server.primary_allocation'))
                    ->hidden(fn () => !user()?->can('update server')) // TODO: update to policy check (fn (Server $server) --> $server is empty)
                    ->disabled(fn (Server $server) => $server->allocations->count() <= 1)
                    ->options(fn (Server $server) => $server->allocations->mapWithKeys(fn ($allocation) => [$allocation->id => $allocation->address]))
                    ->selectablePlaceholder(fn (Server $server) => $server->allocations->count() <= 1)
                    ->placeholder(trans('admin/server.none'))
                    ->sortable(),
                TextColumn::make('allocation_id_readonly')
                    ->label(trans('admin/server.primary_allocation'))
                    ->hidden(fn () => user()?->can('update server')) // TODO: update to policy check (fn (Server $server) --> $server is empty)
                    ->state(fn (Server $server) => $server->allocation->address ?? trans('admin/server.none')),
                TextColumn::make('databases_count')
                    ->counts('databases')
                    ->label(trans('admin/server.databases'))
                    ->numeric()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('backups_count')
                    ->counts('backups')
                    ->label(trans('admin/server.backups'))
                    ->numeric()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->tooltip(trans('filament-actions::view.single.label'))
                    ->hidden(fn (Server $record) => ServerResource::getEditAuthorizationResponse($record)->allowed()),
                ViewConsoleAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ])
            ->searchable()
            ->emptyStateIcon(TablerIcon::BrandDocker)
            ->emptyStateDescription('')
            ->emptyStateHeading(trans('admin/server.no_servers'));
    }
}
