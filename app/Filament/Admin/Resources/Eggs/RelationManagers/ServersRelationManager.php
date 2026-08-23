<?php

namespace App\Filament\Admin\Resources\Eggs\RelationManagers;

use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Components\Actions\ViewConsoleAction;
use App\Models\Server;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServersRelationManager extends RelationManager
{
    protected static string $relationship = 'servers';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('servers')
            ->emptyStateDescription(trans('admin/egg.no_servers'))
            ->emptyStateHeading(trans('admin/egg.no_servers_help'))
            ->heading(trans('admin/egg.servers'))
            ->columns([
                TextColumn::make('user.username')
                    ->label(trans('admin/server.owner'))
                    ->url(fn (Server $server) => user()?->can('update', $server->user) ? EditUser::getUrl(['record' => $server->user]) : null)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label(trans('admin/server.name'))
                    ->url(fn (Server $server) => user()?->can('update', $server) ? EditServer::getUrl(['record' => $server]) : null)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('node.name')
                    ->label(trans('admin/server.node'))
                    ->url(fn (Server $server) => user()?->can('update', $server->node) ? EditNode::getUrl(['record' => $server->node]) : null)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('image')
                    ->label(trans('admin/server.docker_image'))
                    ->badge(),
                TextColumn::make('allocation.address')
                    ->label(trans('admin/server.primary_allocation'))
                    ->placeholder(trans('admin/server.none'))
                    ->sortable(),
            ])
            ->recordActions([
                ViewConsoleAction::make(),
                EditAction::make()
                    ->url(fn (Server $server) => EditServer::getUrl(['record' => $server], panel: 'admin')),
            ]);
    }
}
