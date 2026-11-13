<?php

namespace App\Filament\Server\Resources\Backups;

use App\Enums\BackupStatus;
use App\Enums\ServerState;
use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Components\Tables\Columns\BytesColumn;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Filament\Server\Resources\Backups\Pages\ListBackups;
use App\Http\Controllers\Api\Client\Servers\BackupController;
use App\Models\Backup;
use App\Models\Server;
use App\Repositories\Daemon\DaemonBackupRepository;
use App\Services\Backups\DeleteBackupService;
use App\Services\Backups\DownloadLinkService;
use App\Services\Backups\InitiateBackupService;
use App\Traits\Filament\BlockAccessInConflict;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyForm;
use App\Traits\Filament\CanModifyTable;
use App\Traits\Filament\HasLimitBadge;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class BackupResource extends Resource
{
    use BlockAccessInConflict;
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyForm;
    use CanModifyTable;
    use HasLimitBadge;

    protected static ?string $model = Backup::class;

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::FileZip;

    protected static bool $canCreateAnother = false;

    protected static function getBadgeCount(): int
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server->backups->count();
    }

    protected static function getBadgeLimit(): int
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server->backup_limit ?? 0;
    }

    public static function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(trans('server/backup.actions.create.name'))
                    ->columnSpanFull(),
                Textarea::make('ignored')
                    ->label(trans('server/backup.actions.create.ignored'))
                    ->columnSpanFull(),
                Toggle::make('is_locked')
                    ->label(trans('server/backup.actions.create.locked'))
                    ->helperText(trans('server/backup.actions.create.lock_helper'))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function defaultTable(Table $table): Table
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $table
            ->columns([
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
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('exclude_rename')
                        ->icon(TablerIcon::Pencil)
                        ->authorize(fn () => user()?->can(SubuserPermission::BackupDelete, $server))
                        ->label(trans('server/backup.actions.rename.title'))
                        ->schema([
                            TextInput::make('name')
                                ->label(trans('server/backup.actions.rename.new_name'))
                                ->required()
                                ->maxLength(255)
                                ->default(fn (Backup $backup) => $backup->name),
                        ])
                        ->action(function (Backup $backup, $data) {
                            $oldName = $backup->name;
                            $newName = $data['name'];

                            $backup->update(['name' => $newName]);

                            if ($oldName !== $newName) {
                                Activity::event('server:backup.rename')
                                    ->subject($backup)
                                    ->property(['old_name' => $oldName, 'new_name' => $newName])
                                    ->log();
                            }

                            Notification::make()
                                ->title(trans('server/backup.actions.rename.notification_success'))
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Backup $backup) => $backup->status === BackupStatus::Successful),
                    Action::make('exclude_lock')
                        ->iconSize(IconSize::Large)
                        ->icon(fn (Backup $backup) => !$backup->is_locked ? TablerIcon::Lock : TablerIcon::LockOpen)
                        ->authorize(fn () => user()?->can(SubuserPermission::BackupDelete, $server))
                        ->label(fn (Backup $backup) => !$backup->is_locked ? trans('server/backup.actions.lock.lock') : trans('server/backup.actions.lock.unlock'))
                        ->action(fn (BackupController $backupController, Backup $backup, Request $request) => $backupController->toggleLock($request, $server, $backup))
                        ->visible(fn (Backup $backup) => $backup->status === BackupStatus::Successful),
                    Action::make('exclude_download')
                        ->label(trans('server/backup.actions.download'))
                        ->iconSize(IconSize::Large)
                        ->color('primary')
                        ->icon(TablerIcon::Download)
                        ->authorize(fn () => user()?->can(SubuserPermission::BackupDownload, $server))
                        ->url(fn (DownloadLinkService $downloadLinkService, Backup $backup) => $downloadLinkService->handle($backup, user()), true)
                        ->visible(fn (Backup $backup) => $backup->status === BackupStatus::Successful),
                    Action::make('exclude_restore')
                        ->label(trans('server/backup.actions.restore.title'))
                        ->iconSize(IconSize::Large)
                        ->color('success')
                        ->icon(TablerIcon::FolderUp)
                        ->authorize(fn () => user()?->can(SubuserPermission::BackupRestore, $server))
                        ->schema([
                            TextEntry::make('stop_info')
                                ->hiddenLabel()
                                ->helperText(trans('server/backup.actions.restore.helper')),
                            Checkbox::make('truncate')
                                ->label(trans('server/backup.actions.restore.delete_all')),
                        ])
                        ->action(function (Backup $backup, $data, DaemonBackupRepository $daemonRepository, DownloadLinkService $downloadLinkService) use ($server) {
                            if (!is_null($server->status)) {
                                return Notification::make()
                                    ->title(trans('server/backup.actions.restore.notification_fail'))
                                    ->body(trans('server/backup.actions.restore.notification_fail_body_1'))
                                    ->danger()
                                    ->send();
                            }

                            if ($backup->status !== BackupStatus::Successful) {
                                return Notification::make()
                                    ->title(trans('server/backup.actions.restore.notification_fail'))
                                    ->body(trans('server/backup.actions.restore.notification_fail_body_2'))
                                    ->danger()
                                    ->send();
                            }

                            $log = Activity::event('server:backup.restore')
                                ->subject($backup)
                                ->property(['name' => $backup->name, 'truncate' => $data['truncate']]);

                            $log->transaction(function () use ($downloadLinkService, $daemonRepository, $backup, $server, $data) {
                                $url = $downloadLinkService->handle($backup, user());

                                // Update the status right away for the server so that we know not to allow certain
                                // actions against it via the Panel API.
                                $server->update(['status' => ServerState::RestoringBackup]);

                                $daemonRepository->setServer($server)->restore($backup, $url, $data['truncate']);
                            });

                            return Notification::make()
                                ->title(trans('server/backup.actions.restore.notification_started'))
                                ->send();
                        })
                        ->visible(fn (Backup $backup) => $backup->status === BackupStatus::Successful),
                    Action::make('exclude_delete')
                        ->icon(TablerIcon::Trash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->authorize(fn () => user()?->can(SubuserPermission::BackupDelete, $server))
                        ->label(trans('filament-actions::delete.single.label'))
                        ->iconSize(IconSize::Large)
                        ->disabled(fn (Backup $backup) => $backup->is_locked && $backup->status !== BackupStatus::Failed)
                        ->modalDescription(fn (Backup $backup) => trans('server/backup.actions.delete.description', ['backup' => $backup->name]))
                        ->modalSubmitActionLabel(trans('server/backup.actions.delete.title'))
                        ->successNotificationTitle(null)
                        ->action(function (Backup $backup, DeleteBackupService $deleteBackupService) {
                            try {
                                $deleteBackupService->handle($backup);

                                Notification::make()
                                    ->title(trans('server/backup.actions.delete.notification_success'))
                                    ->success()
                                    ->send();
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
                        })
                        ->visible(fn (Backup $backup) => $backup->status !== BackupStatus::InProgress),
                ])->iconSize(IconSize::Large),
            ])
            ->toolbarActions([
                CreateAction::make()
                    ->authorize(fn () => user()?->can(SubuserPermission::BackupCreate, $server))
                    ->icon(TablerIcon::FileZip)
                    ->tooltip(fn () => $server->backups()->count() >= $server->backup_limit ? trans('server/backup.actions.create.limit') : trans('server/backup.actions.create.title'))
                    ->disabled(fn () => $server->backups()->count() >= $server->backup_limit)
                    ->color(fn () => $server->backups()->count() >= $server->backup_limit ? 'danger' : 'primary')
                    ->createAnother(false)
                    ->hiddenLabel()
                    ->successNotificationTitle(null)
                    ->action(function (InitiateBackupService $initiateBackupService, $data) use ($server) {
                        $action = $initiateBackupService->setIgnoredFiles(explode(PHP_EOL, $data['ignored'] ?? ''));

                        if (user()?->can(SubuserPermission::BackupDelete, $server)) {
                            $action->setIsLocked((bool) $data['is_locked']);
                        }

                        try {
                            $backup = $action->handle($server, $data['name']);

                            Activity::event('server:backup.start')
                                ->subject($backup)
                                ->property(['name' => $backup->name, 'locked' => (bool) $data['is_locked']])
                                ->log();

                            return Notification::make()
                                ->title(trans('server/backup.actions.create.notification_success'))
                                ->body(fn () => trans('server/backup.actions.create.created', ['name' => $backup->name]))
                                ->success()
                                ->send();
                        } catch (HttpException $e) {
                            return Notification::make()
                                ->danger()
                                ->title(trans('server/backup.actions.create.notification_fail'))
                                ->body($e->getMessage() . ' Try again' . ($e->getHeaders()['Retry-After'] ? ' in ' . $e->getHeaders()['Retry-After'] . ' seconds.' : ''))
                                ->send();
                        }
                    }),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListBackups::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return trans('server/backup.title');
    }
}
