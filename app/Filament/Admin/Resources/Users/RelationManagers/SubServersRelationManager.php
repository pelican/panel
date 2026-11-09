<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Components\Actions\ViewConsoleAction;
use App\Filament\Server\Resources\Subusers\Pages\ListSubusers;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The servers a user can reach as a subuser of someone else's server. Many
 * accounts own no server at all and only exist as subusers: without this list
 * their user page reads as if they had no access anywhere.
 */
class SubServersRelationManager extends RelationManager
{
    protected static string $relationship = 'subServers';

    public function table(Table $table): Table
    {
        return $table
            ->heading(trans('admin/user.subuser_servers'))
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'node',
                'user',
                // ViewConsoleAction only looks for the viewer's own subuser.
                'subusers' => fn ($query) => $query->where('user_id', user()?->id),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label(trans('admin/server.name'))
                    ->url(fn (Server $server) => user()?->can('update', $server) ? EditServer::getUrl(['record' => $server]) : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.username')
                    ->label(trans('admin/server.owner'))
                    ->url(fn (Server $server) => user()?->can('update', $server->user) ? EditUser::getUrl(['record' => $server->user]) : null)
                    ->searchable(),
                TextColumn::make('node.name')
                    ->label(trans('admin/server.node'))
                    ->url(fn (Server $server) => user()?->can('update', $server->node) ? EditNode::getUrl(['record' => $server->node]) : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('permissions')
                    ->label(trans('admin/user.permissions'))
                    ->state(fn (Server $server) => count((array) json_decode($server->getRelationValue('pivot')->permissions ?? '[]', true)))
                    ->numeric(),
            ])
            ->emptyStateHeading(trans('admin/user.no_subuser_servers'))
            ->recordActions([
                ViewConsoleAction::make(),
                Action::make('manage_subusers')
                    ->label(trans('admin/user.manage_subusers'))
                    ->icon(TablerIcon::Users)
                    ->iconButton()
                    ->tooltip(trans('admin/user.manage_subusers'))
                    ->authorize(fn (Server $server) => user()?->canAccessTenant($server) && user()->can(SubuserPermission::UserRead, $server))
                    ->url(fn (Server $server) => ListSubusers::getUrl(panel: 'server', tenant: $server)),
            ]);
    }
}
