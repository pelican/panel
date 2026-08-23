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

class BackupsRelationManager extends RelationManager
{
    protected static string $relationship = 'backups';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading(null)
            ->columns([
                TextColumn::make('server.name')
                    ->label(trans('admin/server.name'))
                    ->url(fn (Backup $backup) => user()?->can('update', $backup->server) ? EditServer::getUrl(['record' => $backup->server]) : null)
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
