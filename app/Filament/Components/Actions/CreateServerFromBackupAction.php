<?php

namespace App\Filament\Components\Actions;

use App\Enums\RolePermissionModels;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Servers\Pages\CreateServer;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Services\Backups\BackupConfigResolver;
use App\Services\Backups\RestoreSource;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;

/**
 * Opens the create server wizard filled in from a backup. With a backup record
 * it goes straight there, otherwise it asks where the backup is.
 */
class CreateServerFromBackupAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'create_from_backup';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(trans('admin/backup.restore.action'));

        $this->tooltip(trans('admin/backup.restore.action'));

        $this->icon(TablerIcon::Restore);

        $this->authorize(fn () => user()?->can(RolePermissionModels::Server->create()));

        $this->visible(fn (?Backup $record = null) => !$record || $record->is_successful);

        $this->schema(fn (?Backup $record = null) => $record ? [] : [
            ToggleButtons::make('type')
                ->label(trans('admin/backup.restore.source'))
                ->inline()
                ->live()
                ->required()
                ->default(RestoreSource::TYPE_BACKUP)
                ->options([
                    RestoreSource::TYPE_BACKUP => trans('admin/backup.restore.source_backup'),
                    RestoreSource::TYPE_S3 => trans('admin/backup.restore.source_s3'),
                    RestoreSource::TYPE_URL => trans('admin/backup.restore.source_url'),
                ]),
            Select::make('backup_id')
                ->label(trans('admin/backup.restore.backup'))
                ->visible(fn (Get $get) => $get('type') === RestoreSource::TYPE_BACKUP)
                ->required(fn (Get $get) => $get('type') === RestoreSource::TYPE_BACKUP)
                ->searchable()
                ->getSearchResultsUsing(fn (?string $search) => $this->backupOptions($search))
                ->options(fn () => $this->backupOptions(null))
                // Search can find backups older than the ones listed by default.
                ->getOptionLabelUsing(fn ($value) => ($backup = Backup::query()->restorable()->accessibleBy(user())->with('server')->find($value)) ? $this->backupLabel($backup) : null),
            Select::make('backup_host_id')
                ->label(trans('admin/backup.restore.backup_host'))
                ->visible(fn (Get $get) => $get('type') === RestoreSource::TYPE_S3)
                ->required(fn (Get $get) => $get('type') === RestoreSource::TYPE_S3)
                ->options(fn () => BackupHost::query()->where('schema', 's3')->accessibleBy(user())->pluck('name', 'id')),
            TextInput::make('key')
                ->label(trans('admin/backup.restore.key'))
                ->helperText(trans('admin/backup.restore.key_help'))
                ->placeholder('2f1a…/9c3e….tar.gz')
                ->visible(fn (Get $get) => $get('type') === RestoreSource::TYPE_S3)
                ->required(fn (Get $get) => $get('type') === RestoreSource::TYPE_S3)
                ->endsWith(['.tar.gz']),
            TextInput::make('url')
                ->label(trans('admin/backup.restore.url'))
                ->helperText(trans('admin/backup.restore.url_help'))
                ->visible(fn (Get $get) => $get('type') === RestoreSource::TYPE_URL)
                ->required(fn (Get $get) => $get('type') === RestoreSource::TYPE_URL)
                ->url()
                ->startsWith(['http://', 'https://'])
                // The Panel reads the configuration from the link and the node downloads it,
                // so it may not point at either one's own network.
                ->rule(fn (BackupConfigResolver $resolver) => function (string $attribute, mixed $value, Closure $fail) use ($resolver) {
                    if (is_string($value) && !$resolver->isPublicUrl($value)) {
                        $fail(trans('admin/backup.restore.url_not_public'));
                    }
                }),
        ]);

        $this->action(function (array $data, ?Backup $record = null) {
            $source = match (true) {
                $record !== null => RestoreSource::backup($record),
                $data['type'] === RestoreSource::TYPE_BACKUP => RestoreSource::backup(Backup::query()->restorable()->accessibleBy(user())->findOrFail($data['backup_id'])),
                $data['type'] === RestoreSource::TYPE_S3 => RestoreSource::s3(BackupHost::query()->findOrFail($data['backup_host_id']), $data['key']),
                default => RestoreSource::url($data['url']),
            };

            // Administrators limited to some nodes may only restore from those nodes'
            // backups and backup hosts.
            if (!$source->isAccessibleBy(user())) {
                Notification::make()
                    ->title(trans('admin/backup.restore.source_not_accessible'))
                    ->danger()
                    ->send();

                $this->halt();
            }

            $key = Str::random(16);
            session()->put("restore_source.$key", $source->toArray());

            return redirect(CreateServer::getUrl(['restore' => $key]));
        });
    }

    /**
     * @return array<int, string>
     */
    private function backupOptions(?string $search): array
    {
        return Backup::query()
            ->restorable()
            ->accessibleBy(user())
            ->with('server')
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%$search%")
                ->orWhereHas('server', fn ($query) => $query->where('name', 'like', "%$search%"))))
            ->latest()
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Backup $backup) => [$backup->id => $this->backupLabel($backup)])
            ->all();
    }

    private function backupLabel(Backup $backup): string
    {
        return "{$backup->serverDisplayName()} — $backup->name";
    }
}
