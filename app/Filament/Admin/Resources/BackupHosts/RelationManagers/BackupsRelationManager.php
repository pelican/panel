<?php

namespace App\Filament\Admin\Resources\BackupHosts\RelationManagers;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Components\Tables\Columns\BytesColumn;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Models\Backup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BackupsRelationManager extends RelationManager
{
    protected static string $relationship = 'backups';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading(null)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['server.node']))
            ->columns([
                // Backups kept after their server was deleted have no server to link to.
                TextColumn::make('server.name')
                    ->label(trans('admin/server.name'))
                    ->state(fn (Backup $backup) => $backup->serverDisplayName())
                    ->color(fn (Backup $backup) => $backup->isOrphaned() ? 'gray' : null)
                    ->url(fn (Backup $backup) => $backup->server && user()?->can('update', $backup->server) ? EditServer::getUrl(['record' => $backup->server]) : null)
                    ->searchable(),
                TextColumn::make('name')
                    ->label(trans('server/backup.actions.create.name'))
                    ->searchable(),
                BytesColumn::make('bytes')
                    ->label(trans('server/backup.size')),
                DateTimeColumn::make('created_at')
                    ->label(trans('server/backup.created_at'))
                    ->since()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(trans('server/backup.status'))
                    ->badge(),
                IconColumn::make('is_locked')
                    ->label(trans('server/backup.is_locked'))
                    ->visibleFrom('md')
                    ->trueIcon(TablerIcon::Lock)
                    ->falseIcon(TablerIcon::LockOpen),
            ]);
    }
}
