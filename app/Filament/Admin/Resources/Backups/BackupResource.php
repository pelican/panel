<?php

namespace App\Filament\Admin\Resources\Backups;

use App\Enums\BackupStatus;
use App\Enums\CustomizationKey;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Admin\Resources\Backups\Pages\ListBackups;
use App\Filament\Admin\Resources\Servers\Pages\EditServer;
use App\Filament\Components\Actions\CreateServerFromBackupAction;
use App\Filament\Components\Tables\Columns\BytesColumn;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Models\Backup;
use App\Services\Backups\DeleteBackupService;
use App\Services\Backups\DownloadLinkService;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanModifyTable;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * All backups, including those kept after their server was deleted, so that
 * administrators can create servers from them.
 */
class BackupResource extends Resource
{
    use CanCustomizePages;
    use CanModifyTable;

    protected static ?string $model = Backup::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Restore;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getModelLabel(): string
    {
        return trans_choice('admin/backup.model_label', 1);
    }

    public static function getPluralModelLabel(): string
    {
        return trans_choice('admin/backup.model_label', 2);
    }

    public static function getNavigationGroup(): ?string
    {
        if (user()?->getCustomization(CustomizationKey::TopNavigation) === 'topbar') {
            return null;
        }

        return trans('admin/dashboard.server');
    }

    /**
     * Listed last in its group, under Servers. Items without a sort come first.
     */
    public static function getNavigationSort(): ?int
    {
        return 100;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        // Like servers, admins only see the backups on the nodes they can access.
        return parent::getEloquentQuery()
            ->whereIn('backups.id', Backup::query()->accessibleBy(user())->select('backups.id'))
            ->with(['server', 'node', 'backupHost']);
    }

    /** @throws Exception */
    public static function defaultTable(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('server.name')
                    ->label(trans('admin/backup.server'))
                    ->state(fn (Backup $backup) => $backup->serverDisplayName())
                    ->color(fn (Backup $backup) => $backup->isOrphaned() ? 'gray' : null)
                    ->url(fn (Backup $backup) => $backup->server && user()?->can('update', $backup->server) ? EditServer::getUrl(['record' => $backup->server]) : null),
                TextColumn::make('name')
                    ->label(trans('server/backup.actions.create.name'))
                    ->searchable(),
                // The less important columns are left out on smaller screens, so the row
                // actions fit without scrolling the table sideways.
                TextColumn::make('node.name')
                    ->label(trans('admin/backup.node'))
                    ->visibleFrom('lg'),
                TextColumn::make('backupHost.name')
                    ->label(trans('admin/backup.backup_host'))
                    ->visibleFrom('xl'),
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
                SelectFilter::make('node_id')
                    ->label(trans('admin/backup.node'))
                    ->relationship('node', 'name'),
                SelectFilter::make('backup_host_id')
                    ->label(trans('admin/backup.backup_host'))
                    ->relationship('backupHost', 'name'),
            ])
            ->recordActions([
                CreateServerFromBackupAction::make()
                    ->hiddenLabel(),
                // Kept backups of deleted servers can only be downloaded here.
                Action::make('download')
                    ->hiddenLabel()
                    ->tooltip(trans('server/backup.actions.download'))
                    ->icon(TablerIcon::Download)
                    ->color('primary')
                    ->visible(fn (Backup $backup) => $backup->status === BackupStatus::Successful)
                    ->authorize(fn (Backup $backup) => user()?->can('view', $backup))
                    ->url(fn (Backup $backup, DownloadLinkService $downloadLinkService) => $downloadLinkService->handle($backup, user()), true),
                // The page of a deleted server is gone, so its locked backups are unlocked here.
                Action::make('unlock')
                    ->hiddenLabel()
                    ->tooltip(trans('server/backup.actions.lock.unlock'))
                    ->icon(TablerIcon::LockOpen)
                    ->color('warning')
                    ->visible(fn (Backup $backup) => $backup->isOrphaned() && $backup->is_locked)
                    ->authorize(fn (Backup $backup) => user()?->can('delete', $backup))
                    ->requiresConfirmation()
                    ->action(function (Backup $backup) {
                        $backup->update(['is_locked' => false]);

                        Activity::event('server:backup.unlock')->subject($backup)->property('name', $backup->name)->log();
                    }),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->visible(fn (Backup $backup) => $backup->isOrphaned())
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
            ->toolbarActions([
                CreateServerFromBackupAction::make('create_from_source')
                    ->label(trans('admin/backup.restore.action_source'))
                    ->tooltip(null)
                    ->icon(TablerIcon::FileImport),
            ])
            ->emptyStateIcon(TablerIcon::FileZip)
            ->emptyStateDescription('')
            ->emptyStateHeading(trans('admin/backup.no_backups'));
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListBackups::route('/'),
        ];
    }
}
