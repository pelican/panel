<?php

namespace App\Filament\Admin\Resources\Nodes\RelationManagers;

use App\Enums\ServerResourceType;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Eggs\Pages\EditEgg;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Components\Actions\ViewConsoleAction;
use App\Models\Server;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServersRelationManager extends RelationManager
{
    protected static string $relationship = 'servers';

    protected static string|BackedEnum|null $icon = TablerIcon::BrandDocker;

    public function setTitle(): string
    {
        return trans('admin/node.table.servers');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->columns([
                TextColumn::make('user.username')
                    ->label(trans('admin/node.table.owner'))
                    ->url(fn (Server $server) => user()?->can('update', $server->user) ? EditUser::getUrl(['record' => $server->user]) : null)
                    ->searchable(),
                TextColumn::make('name')
                    ->label(trans('admin/node.table.name'))
                    ->url(fn (Server $server) => user()?->can('update', $server) ? EditServer::getUrl(['record' => $server]) : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('egg.name')
                    ->label(trans('admin/node.table.egg'))
                    ->url(fn (Server $server) => user()?->can('update', $server->egg) ? EditEgg::getUrl(['record' => $server->egg]) : null)
                    ->sortable(),
                SelectColumn::make('allocation.id')
                    ->label(trans('admin/node.primary_allocation'))
                    ->disabled(fn (Server $server) => $this->isReadOnly() || $server->allocations->count() <= 1)
                    ->options(fn (Server $server) => $server->allocations->take(1)->mapWithKeys(fn ($allocation) => [$allocation->id => $allocation->address]))
                    ->selectablePlaceholder(fn (Server $server) => $server->allocations->count() <= 1)
                    ->placeholder(trans('admin/server.none')),
                TextColumn::make('cpu')
                    ->label(trans('admin/node.cpu'))
                    ->state(fn (Server $server) => $server->formatResource(ServerResourceType::CPULimit)),
                TextColumn::make('memory')
                    ->label(trans('admin/node.memory'))
                    ->state(fn (Server $server) => $server->formatResource(ServerResourceType::MemoryLimit)),
                TextColumn::make('disk')
                    ->label(trans('admin/node.disk'))
                    ->state(fn (Server $server) => $server->formatResource(ServerResourceType::DiskLimit)),
                TextColumn::make('databases_count')
                    ->counts('databases')
                    ->label(trans('admin/node.databases'))
                    ->numeric()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('backups_count')
                    ->counts('backups')
                    ->label(trans('admin/node.backups'))
                    ->numeric()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->emptyStateHeading(trans('admin/server.no_servers'))
            ->recordActions([
                ViewConsoleAction::make(),
                EditAction::make()
                    ->url(fn (Server $server) => EditServer::getUrl(['record' => $server], panel: 'admin')),
            ]);
    }
}
