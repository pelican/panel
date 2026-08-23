<?php

namespace App\Filament\Admin\Resources\DatabaseHosts\RelationManagers;

use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Components\Actions\RotateDatabasePasswordAction;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Models\Database;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DatabasesRelationManager extends RelationManager
{
    protected static string $relationship = 'databases';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('database')
                    ->columnSpanFull(),
                TextInput::make('username')
                    ->label(trans('admin/databasehost.table.username')),
                TextInput::make('password')
                    ->label(trans('admin/databasehost.table.password'))
                    ->password()
                    ->revealable()
                    ->hintAction(RotateDatabasePasswordAction::make())
                    ->formatStateUsing(fn (Database $database) => $database->password),
                TextInput::make('remote')
                    ->label(trans('admin/databasehost.table.remote'))
                    ->formatStateUsing(fn (Database $record) => $record->remote === '%' ? trans('admin/databasehost.anywhere'). ' ( % )' : $record->remote),
                TextInput::make('max_connections')
                    ->label(trans('admin/databasehost.table.max_connections'))
                    ->formatStateUsing(fn (Database $record) => $record->max_connections ?: trans('admin/databasehost.unlimited')),
                TextInput::make('jdbc')
                    ->label(trans('admin/databasehost.table.connection_string'))
                    ->columnSpanFull()
                    ->password()
                    ->revealable()
                    ->formatStateUsing(fn (Database $database) => $database->jdbc),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('database')
            ->heading(null)
            ->columns([
                TextColumn::make('database'),
                TextColumn::make('username')
                    ->label(trans('admin/databasehost.table.username')),
                TextColumn::make('remote')
                    ->label(trans('admin/databasehost.table.remote'))
                    ->formatStateUsing(fn (Database $record) => $record->remote === '%' ? trans('admin/databasehost.anywhere'). ' ( % )' : $record->remote),
                TextColumn::make('server.name')
                    ->url(fn (Database $database) => user()?->can('update', $database->server) ? EditServer::getUrl(['record' => $database->server]) : null),
                TextColumn::make('max_connections')
                    ->label(trans('admin/databasehost.table.max_connections'))
                    ->formatStateUsing(fn ($record) => $record->max_connections ?: trans('server/database.unlimited')),
                DateTimeColumn::make('created_at')
                    ->label(trans('admin/databasehost.table.created_at')),
            ])
            ->emptyStateHeading(trans('admin/databasehost.no_databases'))
            ->recordActions([
                ViewAction::make()
                    ->color('primary'),
                DeleteAction::make(),
            ]);
    }
}
