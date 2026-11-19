<?php

namespace App\Filament\Admin\Resources\Servers\RelationManagers;

use App\Enums\BackupStatus;
use App\Enums\ServerState;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Components\Tables\Columns\BytesColumn;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Models\Backup;
use App\Models\Server;
use App\Repositories\Daemon\DaemonBackupRepository;
use App\Services\Backups\DeleteBackupService;
use App\Services\Backups\DownloadLinkService;
use App\Services\Backups\ServerRestoreService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;

/**
 * @method Server getOwnerRecord()
 */
class BackupsRelationManager extends RelationManager
{
    protected static string $relationship = 'backups';

    protected static string|BackedEnum|null $icon = TablerIcon::FileZip;

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['backupHost']))
            ->columns([
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
                IconColumn::make('is_locked')
                    ->label(trans('server/backup.is_locked'))
                    ->visibleFrom('md')
                    ->trueIcon(TablerIcon::Lock)
                    ->falseIcon(TablerIcon::LockOpen),
            ])
            ->recordActions([
                Action::make('download')
                    ->hiddenLabel()
                    ->tooltip(trans('server/backup.actions.download'))
                    ->icon(TablerIcon::Download)
                    ->color('primary')
                    ->visible(fn (Backup $backup) => $backup->status === BackupStatus::Successful)
                    ->authorize(fn (Backup $backup) => user()?->can('view', $backup))
                    ->url(fn (Backup $backup, DownloadLinkService $downloadLinkService) => $downloadLinkService->handle($backup, user()), true),
                // Locking keeps a backup from being deleted, so it is gated like delete.
                Action::make('toggle_lock')
                    ->hiddenLabel()
                    ->tooltip(fn (Backup $backup) => $backup->is_locked ? trans('server/backup.actions.lock.unlock') : trans('server/backup.actions.lock.lock'))
                    ->icon(fn (Backup $backup) => $backup->is_locked ? TablerIcon::LockOpen : TablerIcon::Lock)
                    ->color('warning')
                    ->visible(fn (Backup $backup) => !$this->isReadOnly() && $backup->status === BackupStatus::Successful)
                    ->authorize(fn (Backup $backup) => user()?->can('delete', $backup))
                    ->action(function (Backup $backup) {
                        $event = $backup->is_locked ? 'server:backup.unlock' : 'server:backup.lock';

                        $backup->update(['is_locked' => !$backup->is_locked]);

                        Activity::event($event)->subject($backup)->property('name', $backup->name)->log();
                    }),
                Action::make('restore')
                    ->hiddenLabel()
                    ->tooltip(trans('server/backup.actions.restore.title'))
                    ->modalHeading(trans('server/backup.actions.restore.title'))
                    ->icon(TablerIcon::FolderUp)
                    ->color('success')
                    ->visible(fn (Backup $backup) => !$this->isReadOnly() && $backup->status === BackupStatus::Successful)
                    ->authorize(fn () => user()?->can('update', $this->getOwnerRecord()))
                    ->schema([
                        TextEntry::make('stop_info')
                            ->hiddenLabel()
                            ->helperText(trans('server/backup.actions.restore.helper')),
                        Checkbox::make('truncate')
                            ->label(trans('server/backup.actions.restore.delete_all')),
                    ])
                    ->action(function (Backup $backup, array $data, DaemonBackupRepository $daemonRepository, DownloadLinkService $downloadLinkService, ServerRestoreService $restoreService) {
                        $server = $this->getOwnerRecord();

                        if (!is_null($server->status)) {
                            return Notification::make()
                                ->title(trans('server/backup.actions.restore.notification_fail'))
                                ->body(trans('server/backup.actions.restore.notification_fail_body_1'))
                                ->danger()
                                ->send();
                        }

                        $log = Activity::event('server:backup.restore')
                            ->subject($backup)
                            ->property(['name' => $backup->name, 'truncate' => $data['truncate']]);

                        // The node reports this restore with the backup's uuid, like a restore of the backup
                        // into a new server on the same node, so only one of them may start at a time.
                        $started = $restoreService->whileRestoring($backup, function () use ($restoreService, $log, $downloadLinkService, $daemonRepository, $backup, $server, $data) {
                            if ($restoreService->isRestoringIntoNewServer($backup, $server->node_id)) {
                                return false;
                            }

                            $log->transaction(function () use ($downloadLinkService, $daemonRepository, $backup, $server, $data) {
                                $url = $downloadLinkService->handle($backup, user());

                                // Mark the server right away so the Panel API blocks other actions against it.
                                $server->update(['status' => ServerState::RestoringBackup]);

                                $daemonRepository->setServer($server)->restore($backup, $url, $data['truncate']);
                            });

                            return true;
                        });

                        if (!$started) {
                            return Notification::make()
                                ->title(trans('server/backup.actions.restore.notification_fail'))
                                ->body(trans('admin/backup.restore.restoring_into_new_server'))
                                ->danger()
                                ->send();
                        }

                        return Notification::make()
                            ->title(trans('server/backup.actions.restore.notification_started'))
                            ->send();
                    }),
                Action::make('delete')
                    ->hiddenLabel()
                    ->tooltip(trans('filament-actions::delete.single.label'))
                    ->icon(TablerIcon::Trash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Backup $backup) => !$this->isReadOnly() && $backup->status !== BackupStatus::InProgress)
                    ->authorize(fn (Backup $backup) => user()?->can('delete', $backup))
                    ->disabled(fn (Backup $backup) => $backup->is_locked && $backup->status !== BackupStatus::Failed)
                    ->modalHeading(trans('server/backup.actions.delete.title'))
                    ->modalDescription(fn (Backup $backup) => trans('server/backup.actions.delete.description', ['backup' => $backup->name]))
                    ->modalSubmitActionLabel(trans('server/backup.actions.delete.title'))
                    ->action(function (Backup $backup, DeleteBackupService $deleteBackupService) {
                        try {
                            $deleteBackupService->handle($backup);
                        } catch (ConnectionException) {
                            Notification::make()
                                ->title(trans('server/backup.actions.delete.notification_fail'))
                                ->body(trans('server/backup.actions.delete.notification_fail_body'))
                                ->danger()
                                ->send();

                            return;
                        }

                        Activity::event('server:backup.delete')
                            ->subject($backup)
                            ->property(['name' => $backup->name, 'failed' => !$backup->is_successful])
                            ->log();

                        Notification::make()
                            ->title(trans('server/backup.actions.delete.notification_success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateIcon(TablerIcon::FileZip)
            ->emptyStateDescription('')
            ->emptyStateHeading(trans('admin/backup.no_backups'));
    }
}
