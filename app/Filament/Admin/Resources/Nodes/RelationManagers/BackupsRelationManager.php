<?php

namespace App\Filament\Admin\Resources\Nodes\RelationManagers;

use App\Enums\BackupStatus;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Components\Actions\CreateServerFromBackupAction;
use App\Filament\Components\Tables\Columns\BytesColumn;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Models\Backup;
use App\Models\Node;
use App\Services\Backups\DeleteBackupService;
use App\Services\Backups\DownloadLinkService;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * All backups on a node, including the ones kept after their server was deleted.
 *
 * @method Node getOwnerRecord()
 */
class BackupsRelationManager extends RelationManager
{
    protected static string $relationship = 'backups';

    protected static string|BackedEnum|null $icon = TablerIcon::FileZip;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans_choice('admin/backup.model_label', 2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['server', 'node', 'backupHost']))
            ->columns([
                TextColumn::make('server.name')
                    ->label(trans('admin/backup.server'))
                    ->state(fn (Backup $backup) => $backup->serverDisplayName())
                    ->color(fn (Backup $backup) => $backup->isOrphaned() ? 'gray' : null)
                    ->url(fn (Backup $backup) => $backup->server && user()?->can('update', $backup->server) ? EditServer::getUrl(['record' => $backup->server]) : null),
                TextColumn::make('name')
                    ->label(trans('server/backup.actions.create.name'))
                    ->searchable(),
                TextColumn::make('backupHost.name')
                    ->label(trans('admin/backup.backup_host'))
                    ->visibleFrom('lg'),
                BytesColumn::make('bytes')
                    ->label(trans('server/backup.size')),
                DateTimeColumn::make('created_at')
                    ->label(trans('server/backup.created_at'))
                    ->since()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(trans('server/backup.status'))
                    ->badge(),
                IconColumn::make('has_config')
                    ->label(trans('admin/backup.has_config'))
                    ->state(fn (Backup $backup) => $backup->pelican_config !== null)
                    ->boolean()
                    ->visibleFrom('xl'),
            ])
            ->filters([
                TernaryFilter::make('orphaned')
                    ->label(trans('admin/backup.deleted_servers'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('server_id'),
                        false: fn (Builder $query) => $query->whereNotNull('server_id'),
                    ),
                SelectFilter::make('backup_host_id')
                    ->label(trans('admin/backup.backup_host'))
                    ->relationship('backupHost', 'name'),
            ])
            ->recordActions([
                CreateServerFromBackupAction::make()
                    ->hiddenLabel()
                    ->hidden(fn () => $this->isReadOnly()),
                Action::make('download')
                    ->hiddenLabel()
                    ->tooltip(trans('server/backup.actions.download'))
                    ->icon(TablerIcon::Download)
                    ->color('primary')
                    ->visible(fn (Backup $backup) => $backup->status === BackupStatus::Successful)
                    ->authorize(fn (Backup $backup) => user()?->can('view', $backup))
                    ->url(fn (Backup $backup, DownloadLinkService $downloadLinkService) => $downloadLinkService->handle($backup, user()), true),
                // Backups of servers that still exist are locked and deleted from the
                // server's page, like on the Backups page.
                Action::make('unlock')
                    ->hiddenLabel()
                    ->tooltip(trans('server/backup.actions.lock.unlock'))
                    ->icon(TablerIcon::LockOpen)
                    ->color('warning')
                    ->visible(fn (Backup $backup) => !$this->isReadOnly() && $backup->isOrphaned() && $backup->is_locked)
                    ->authorize(fn (Backup $backup) => user()?->can('delete', $backup))
                    ->requiresConfirmation()
                    ->action(function (Backup $backup) {
                        $backup->update(['is_locked' => false]);

                        Activity::event('server:backup.unlock')->subject($backup)->property('name', $backup->name)->log();
                    }),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->visible(fn (Backup $backup) => !$this->isReadOnly() && $backup->isOrphaned())
                    ->authorize(fn (Backup $backup) => user()?->can('delete', $backup))
                    ->using(function (Backup $backup, DeleteBackupService $deleteBackupService) {
                        try {
                            $deleteBackupService->handle($backup);
                        } catch (Exception $exception) {
                            Notification::make()
                                ->danger()
                                ->title(trans('admin/backup.delete_failed'))
                                ->body($exception->getMessage())
                                ->send();

                            return false;
                        }

                        return true;
                    }),
            ])
            ->emptyStateIcon(TablerIcon::FileZip)
            ->emptyStateDescription('')
            ->emptyStateHeading(trans('admin/backup.no_backups'));
    }
}
