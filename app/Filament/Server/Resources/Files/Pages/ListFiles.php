<?php

namespace App\Filament\Server\Resources\Files\Pages;

use App\Enums\CustomizationKey;
use App\Enums\EditorLanguages;
use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Exceptions\Repository\FileExistsException;
use App\Facades\Activity;
use App\Filament\Components\Actions\ConnectSftpAction;
use App\Filament\Components\Forms\Fields\MonacoEditor;
use App\Filament\Components\Tables\Columns\BytesColumn;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Filament\Server\Resources\Files\FileResource;
use App\Livewire\AlertBanner;
use App\Models\File;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\IconSize;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route as RouteFacade;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class ListFiles extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = FileResource::class;

    protected string $view = 'filament.server.pages.list-files';

    #[Locked]
    public string $path = '/';

    private DaemonFileRepository $fileRepository;

    public function getTitle(): string
    {
        return trans('server/file.title');
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        $breadcrumbs = [
            $resource::getUrl() => $resource::getBreadcrumb(),
        ];

        $previousParts = '';
        foreach (explode('/', $this->path) as $part) {
            $previousParts = $previousParts . '/' . $part;
            $breadcrumbs[self::getUrl(['path' => ltrim($previousParts, '/')])] = $part;
        }

        return $breadcrumbs;
    }

    /**
     * @throws Exception
     */
    public function table(Table $table): Table
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $table
            ->paginated([25, 50, 100, 150, 200])
            ->paginationMode(PaginationMode::Simple)
            ->defaultPaginationPageOption(50)
            ->deferLoading()
            ->searchable()
            ->query(fn () => File::get($server, $this->path)->orderByDesc('is_directory'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(trans('server/file.name'))
                    ->searchable()
                    ->sortable()
                    ->icon(fn (File $file) => $file->getIcon()),
                BytesColumn::make('size')
                    ->label(trans('server/file.size'))
                    ->visibleFrom('md')
                    ->state(fn (File $file) => $file->is_directory ? null : $file->size)
                    ->sortable(),
                DateTimeColumn::make('modified_at')
                    ->label(trans('server/file.modified_at'))
                    ->visibleFrom('md')
                    ->since()
                    ->sortable(),
            ])
            ->recordUrl(function (File $file) use ($server) {
                if ($file->is_directory) {
                    return self::getUrl(['path' => encode_path(join_paths($this->path, $file->name))]);
                }

                if (!user()?->can(SubuserPermission::FileReadContent, $server)) {
                    return null;
                }

                return $file->canEdit() ? EditFiles::getUrl(['path' => encode_path(join_paths($this->path, $file->name))]) : null;
            })
            ->recordActions([
                Action::make('view')
                    ->authorize(fn () => user()?->can(SubuserPermission::FileRead, $server))
                    ->label(trans('server/file.actions.open'))
                    ->icon(TablerIcon::Eye)
                    ->visible(fn (File $file) => $file->is_directory)
                    ->url(fn (File $file) => self::getUrl(['path' => encode_path(join_paths($this->path, $file->name))])),
                EditAction::make('edit')
                    ->authorize(fn () => user()?->can(SubuserPermission::FileReadContent, $server))
                    ->visible(fn (File $file) => $file->canEdit())
                    ->url(fn (File $file) => EditFiles::getUrl(['path' => encode_path(join_paths($this->path, $file->name))])),
                ActionGroup::make([
                    Action::make('fm_rename')
                        ->authorize(fn () => user()?->can(SubuserPermission::FileUpdate, $server))
                        ->label(trans('server/file.actions.rename.title'))
                        ->icon(TablerIcon::Forms)->iconSize(IconSize::Large)
                        ->schema([
                            TextInput::make('name')
                                ->label(trans('server/file.actions.rename.file_name'))
                                ->default(fn (File $file) => $file->name)
                                ->required(),
                        ])
                        ->action(function ($data, File $file) {
                            $files = [['to' => $data['name'], 'from' => $file->name]];

                            $this->getDaemonFileRepository()->renameFiles($this->path, $files);

                            Activity::event('server:file.rename')
                                ->property('directory', $this->path)
                                ->property('files', $files)
                                ->property('to', $data['name'])
                                ->property('from', $file->name)
                                ->log();

                            Notification::make()
                                ->title(trans('server/file.actions.rename.notification'))
                                ->body(fn () => $file->name . ' -> ' . $data['name'])
                                ->success()
                                ->send();

                            $this->refreshPage();
                        }),
                    Action::make('fm_copy')
                        ->authorize(fn () => user()?->can(SubuserPermission::FileCreate, $server))
                        ->label(trans('server/file.actions.copy.title'))
                        ->icon(TablerIcon::Copy)->iconSize(IconSize::Large)
                        ->visible(fn (File $file) => $file->is_file)
                        ->action(function (File $file) {
                            $this->getDaemonFileRepository()->copyFile(join_paths($this->path, $file->name));

                            Activity::event('server:file.copy')
                                ->property('file', join_paths($this->path, $file->name))
                                ->log();

                            Notification::make()
                                ->title(trans('server/file.actions.copy.notification'))
                                ->success()
                                ->send();

                            $this->refreshPage();
                        }),
                    Action::make('fm_download')
                        ->authorize(fn () => user()?->can(SubuserPermission::FileReadContent, $server))
                        ->label(trans('server/file.actions.download'))
                        ->icon(TablerIcon::Download)->iconSize(IconSize::Large)
                        ->visible(fn (File $file) => $file->is_file)
                        ->url(fn (File $file) => DownloadFiles::getUrl(['path' => encode_path(join_paths($this->path, $file->name))]), true),
                    Action::make('fm_move')
                        ->authorize(fn () => user()?->can(SubuserPermission::FileUpdate, $server))
                        ->label(trans('server/file.actions.move.title'))
                        ->icon(TablerIcon::Replace)->iconSize(IconSize::Large)
                        ->schema([
                            TextInput::make('location')
                                ->label(trans('server/file.actions.move.new_location'))
                                ->hint(trans('server/file.actions.move.new_location_hint'))
                                ->required()
                                ->live(),
                            TextEntry::make('new_location')
                                ->state(fn (Get $get, File $file) => resolve_path(join_paths($this->path, str_ends_with($get('location') ?? '/', '/') ? join_paths($get('location') ?? '/', $file->name) : $get('location') ?? '/'))),
                        ])
                        ->action(function ($data, File $file) {
                            $location = $data['location'];
                            $endsWithSlash = str_ends_with($location, '/');
                            $to = $endsWithSlash ? join_paths($location, $file->name) : $location;
                            $files = [['to' => $to, 'from' => $file->name]];

                            $this->getDaemonFileRepository()->renameFiles($this->path, $files);

                            $oldLocation = join_paths($this->path, $file->name);
                            $newLocation = resolve_path(join_paths($this->path, $to));

                            Activity::event('server:file.rename')
                                ->property('directory', $this->path)
                                ->property('files', $files)
                                ->property('to', $newLocation)
                                ->property('from', $oldLocation)
                                ->log();

                            Notification::make()
                                ->title(trans('server/file.actions.move.notification'))
                                ->body($oldLocation . ' -> ' . $newLocation)
                                ->success()
                                ->send();

                            $this->refreshPage();
                        }),
                    Action::make('fm_permissions')
                        ->authorize(fn () => user()?->can(SubuserPermission::FileUpdate, $server))
                        ->label(trans('server/file.actions.permissions.title'))
                        ->icon(TablerIcon::License)->iconSize(IconSize::Large)
                        ->schema([
                            CheckboxList::make('owner')
                                ->label(trans('server/file.actions.permissions.owner'))
                                ->bulkToggleable()
                                ->columns(3)
                                ->options([
                                    'read' => trans('server/file.actions.permissions.read'),
                                    'write' => trans('server/file.actions.permissions.write'),
                                    'execute' => trans('server/file.actions.permissions.execute'),
                                ])
                                ->formatStateUsing(function ($state, File $file) {
                                    $mode = (int) substr((string) $file->mode_bits, 0, 1);

                                    return $this->getPermissionsFromModeBit($mode);
                                }),
                            CheckboxList::make('group')
                                ->label(trans('server/file.actions.permissions.group'))
                                ->bulkToggleable()
                                ->columns(3)
                                ->options([
                                    'read' => trans('server/file.actions.permissions.read'),
                                    'write' => trans('server/file.actions.permissions.write'),
                                    'execute' => trans('server/file.actions.permissions.execute'),
                                ])
                                ->formatStateUsing(function ($state, File $file) {
                                    $mode = (int) substr((string) $file->mode_bits, 1, 1);

                                    return $this->getPermissionsFromModeBit($mode);
                                }),
                            CheckboxList::make('public')
                                ->label(trans('server/file.actions.permissions.public'))
                                ->bulkToggleable()
                                ->columns(3)
                                ->options([
                                    'read' => trans('server/file.actions.permissions.read'),
                                    'write' => trans('server/file.actions.permissions.write'),
                                    'execute' => trans('server/file.actions.permissions.execute'),
                                ])
                                ->formatStateUsing(function ($state, File $file) {
                                    $mode = (int) substr((string) $file->mode_bits, 2, 1);

                                    return $this->getPermissionsFromModeBit($mode);
                                }),
                        ])
                        ->action(function ($data, File $file) {
                            $owner = (in_array('read', $data['owner']) ? 4 : 0) | (in_array('write', $data['owner']) ? 2 : 0) | (in_array('execute', $data['owner']) ? 1 : 0);
                            $group = (in_array('read', $data['group']) ? 4 : 0) | (in_array('write', $data['group']) ? 2 : 0) | (in_array('execute', $data['group']) ? 1 : 0);
                            $public = (in_array('read', $data['public']) ? 4 : 0) | (in_array('write', $data['public']) ? 2 : 0) | (in_array('execute', $data['public']) ? 1 : 0);

                            $mode = $owner . $group . $public;

                            $this->getDaemonFileRepository()->chmodFiles($this->path, [['file' => $file->name, 'mode' => $mode]]);

                            Notification::make()
                                ->title(trans('server/file.actions.permissions.notification', ['mode' => $mode]))
                                ->success()
                                ->send();
                        }),
                    Action::make('fm_archive')
                        ->authorize(fn () => user()?->can(SubuserPermission::FileArchive, $server))
                        ->label(trans('server/file.actions.archive.title'))
                        ->icon(TablerIcon::Archive)->iconSize(IconSize::Large)
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    TextInput::make('name')
                                        ->label(trans('server/file.actions.archive.archive_name'))
                                        ->placeholder(fn () => 'archive-' . str(Carbon::now()->toRfc3339String())->replace(':', '')->before('+0000') . 'Z')
                                        ->columnSpan(2),
                                    Select::make('extension')
                                        ->label(trans('server/file.actions.archive.extension'))
                                        ->selectablePlaceholder(false)
                                        ->options([
                                            'tar.gz' => 'tar.gz',
                                            'zip' => 'zip',
                                            'tar.bz2' => 'tar.bz2',
                                            'tar.xz' => 'tar.xz',
                                        ])
                                        ->columnSpan(1),
                                ]),
                        ])
                        ->action(function ($data, File $file) {
                            $archive = $this->getDaemonFileRepository()->compressFiles($this->path, [$file->name], $data['name'], $data['extension']);

                            Activity::event('server:file.compress')
                                ->property('name', $archive['name'])
                                ->property('directory', $this->path)
                                ->property('files', [$file->name])
                                ->log();

                            Notification::make()
                                ->title(trans('server/file.actions.archive.notification'))
                                ->body($archive['name'])
                                ->success()
                                ->send();

                            $this->refreshPage();
                        }),
                    Action::make('fm_unarchive')
                        ->authorize(fn () => user()?->can(SubuserPermission::FileArchive, $server))
                        ->label(trans('server/file.actions.unarchive.title'))
                        ->icon(TablerIcon::Archive)->iconSize(IconSize::Large)
                        ->visible(fn (File $file) => $file->isArchive())
                        ->action(function (File $file) {
                            $this->getDaemonFileRepository()->decompressFile($this->path, $file->name);

                            Activity::event('server:file.decompress')
                                ->property('directory', $this->path)
                                ->property('file', $file->name)
                                ->log();

                            Notification::make()
                                ->title(trans('server/file.actions.unarchive.notification'))
                                ->success()
                                ->send();

                            $this->refreshPage();
                        }),
                ])->iconSize(IconSize::Large),
                DeleteAction::make()
                    ->authorize(fn () => user()?->can(SubuserPermission::FileDelete, $server))
                    ->requiresConfirmation()
                    ->modalHeading(fn (File $file) => trans('filament-actions::delete.single.modal.heading', ['label' => $file->name . ' ' . ($file->is_directory ? 'folder' : 'file')]))
                    ->action(function (File $file) {
                        $this->deselectAllTableRecords();
                        $this->getDaemonFileRepository()->deleteFiles($this->path, [$file->name]);

                        Activity::event('server:file.delete')
                            ->property('directory', $this->path)
                            ->property('files', $file->name)
                            ->log();

                        $this->refreshPage();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('fm_move')
                        ->label(trans('server/file.actions.move.title'))
                        ->icon(TablerIcon::Replace)->iconSize(IconSize::Large)
                        ->authorize(fn () => user()?->can(SubuserPermission::FileUpdate, $server))
                        ->schema([
                            TextInput::make('location')
                                ->label(trans('server/file.actions.move.directory'))
                                ->hint(trans('server/file.actions.move.directory_hint'))
                                ->required()
                                ->live(),
                            TextEntry::make('new_location')
                                ->state(fn (Get $get) => resolve_path('./' . join_paths($this->path, $get('location') ?? ''))),
                        ])
                        ->action(function (Collection $files, $data) {
                            $location = $data['location'];

                            $files = $files->map(fn ($file) => ['to' => join_paths($location, $file['name']), 'from' => $file['name']])->toArray();
                            $this->getDaemonFileRepository()->renameFiles($this->path, $files);

                            Activity::event('server:file.rename')
                                ->property('directory', $this->path)
                                ->property('files', $files)
                                ->log();

                            Notification::make()
                                ->title(trans('server/file.actions.move.bulk_notification', ['count' => count($files), 'directory' => resolve_path(join_paths($this->path, $location))]))
                                ->success()
                                ->send();

                            $this->refreshPage();
                        }),
                    BulkAction::make('fm_archive')
                        ->label(trans('server/file.actions.archive.title'))
                        ->icon(TablerIcon::Archive)->iconSize(IconSize::Large)
                        ->authorize(fn () => user()?->can(SubuserPermission::FileArchive, $server))
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    TextInput::make('name')
                                        ->label(trans('server/file.actions.archive.archive_name'))
                                        ->placeholder(fn () => 'archive-' . str(Carbon::now()->toRfc3339String())->replace(':', '')->before('+0000') . 'Z')
                                        ->columnSpan(2),
                                    Select::make('extension')
                                        ->label(trans('server/file.actions.archive.extension'))
                                        ->selectablePlaceholder(false)
                                        ->options([
                                            'tar.gz' => 'tar.gz',
                                            'zip' => 'zip',
                                            'tar.bz2' => 'tar.bz2',
                                            'tar.xz' => 'tar.xz',
                                        ])
                                        ->columnSpan(1),
                                ]),
                        ])
                        ->action(function ($data, Collection $files) {
                            $files = $files->map(fn ($file) => $file['name'])->toArray();

                            $archive = $this->getDaemonFileRepository()->compressFiles($this->path, $files, $data['name'], $data['extension']);

                            Activity::event('server:file.compress')
                                ->property('name', $archive['name'])
                                ->property('directory', $this->path)
                                ->property('files', $files)
                                ->log();

                            Notification::make()
                                ->title(trans('server/file.actions.archive.notification'))
                                ->body($archive['name'])
                                ->success()
                                ->send();

                            $this->refreshPage();
                        }),
                    DeleteBulkAction::make('fm_delete')
                        ->successNotificationTitle(null)
                        ->authorize(fn () => user()?->can(SubuserPermission::FileDelete, $server))
                        ->action(function (Collection $files) {
                            $files = $files->map(fn ($file) => $file['name'])->toArray();
                            $this->getDaemonFileRepository()->deleteFiles($this->path, $files);

                            Activity::event('server:file.delete')
                                ->property('directory', $this->path)
                                ->property('files', $files)
                                ->log();

                            Notification::make()
                                ->title(trans('server/file.actions.delete.bulk_notification', ['count' => count($files)]))
                                ->success()
                                ->send();

                            $this->refreshPage();
                        }),
                ]),

                Action::make('new_file')
                    ->authorize(fn () => user()?->can(SubuserPermission::FileCreate, $server))
                    ->tooltip(trans('server/file.actions.new_file.title'))
                    ->hiddenLabel()->icon(TablerIcon::FilePlus)
                    ->color('primary')
                    ->modalSubmitActionLabel(trans('server/file.actions.new_file.create'))
                    ->action(function ($data) {
                        $path = join_paths($this->path, $data['name']);
                        try {
                            $this->getDaemonFileRepository()->putContent($path, $data['editor'] ?? '');

                            Activity::event('server:file.write')
                                ->property('file', $path)
                                ->log();

                            $this->refreshPage();
                        } catch (FileExistsException) {
                            AlertBanner::make('file_already_exists')
                                ->title(trans('server/file.alerts.file_already_exists.title', ['name' => $path]))
                                ->danger()
                                ->closable()
                                ->send();

                            $this->refreshPage(true);
                        }
                    })
                    ->schema([
                        TextInput::make('name')
                            ->label(trans('server/file.actions.new_file.file_name'))
                            ->required(),
                        Select::make('lang')
                            ->label(trans('server/file.actions.new_file.syntax'))
                            ->searchable()
                            ->live()
                            ->options(EditorLanguages::class)
                            ->selectablePlaceholder(false)
                            ->afterStateUpdated(fn ($state) => $this->dispatch('setLanguage', lang: $state))
                            ->default(EditorLanguages::plaintext->value),
                        MonacoEditor::make('editor')
                            ->hiddenLabel()
                            ->language(fn (Get $get) => $get('lang') ?? 'plaintext'),
                    ]),
                Action::make('new_folder')
                    ->authorize(fn () => user()?->can(SubuserPermission::FileCreate, $server))
                    ->hiddenLabel()->icon(TablerIcon::FolderPlus)
                    ->tooltip(trans('server/file.actions.new_folder.title'))
                    ->color('primary')
                    ->action(function ($data) {
                        try {
                            $this->getDaemonFileRepository()->createDirectory($data['name'], $this->path);

                            Activity::event('server:file.create-directory')
                                ->property(['directory' => $this->path, 'name' => $data['name']])
                                ->log();

                            $this->refreshPage();
                        } catch (FileExistsException) {
                            $path = join_paths($this->path, $data['name']);

                            AlertBanner::make('folder_already_exists')
                                ->title(trans('server/file.alerts.file_already_exists.title', ['name' => $path]))
                                ->danger()
                                ->closable()
                                ->send();

                            $this->refreshPage(true);
                        }
                    })
                    ->schema([
                        TextInput::make('name')
                            ->label(trans('server/file.actions.new_folder.folder_name'))
                            ->required(),
                    ]),
                Action::make('uploadFile')
                    ->authorize(fn () => user()?->can(SubuserPermission::FileCreate, $server))
                    ->view('filament.server.pages.file-upload'),
                Action::make('uploadURL')
                    ->authorize(fn () => user()?->can(SubuserPermission::FileCreate, $server))
                    ->hiddenLabel()->icon(TablerIcon::WorldDownload)
                    ->tooltip(trans('server/file.actions.upload.from_url'))
                    ->modalHeading(trans('server/file.actions.upload.from_url'))
                    ->color('success')
                    ->action(function ($data) {
                        $this->getDaemonFileRepository()->pull($data['url'], $this->path);

                        Activity::event('server:file.pull')
                            ->property('url', $data['url'])
                            ->property('directory', $this->path)
                            ->log();

                        $this->refreshPage();
                    })
                    ->schema([
                        TextInput::make('url')
                            ->label(trans('server/file.actions.upload.url'))
                            ->required()
                            ->url(),
                    ]),
                Action::make('search')
                    ->authorize(fn () => user()?->can(SubuserPermission::FileRead, $server))
                    ->hiddenLabel()
                    ->tooltip(trans('server/file.actions.nested_search.title'))
                    ->color('primary')
                    ->icon(TablerIcon::FolderSearch)
                    ->modalHeading(trans('server/file.actions.nested_search.title'))
                    ->modalSubmitActionLabel(trans('server/file.actions.nested_search.search'))
                    ->schema([
                        TextInput::make('searchTerm')
                            ->label(trans('server/file.actions.nested_search.search_term'))
                            ->placeholder(trans('server/file.actions.nested_search.search_term_placeholder'))
                            ->required()
                            ->regex('/^[^*]*\*?[^*]*$/')
                            ->minValue(3),
                    ])
                    ->action(fn ($data) => redirect(SearchFiles::getUrl([
                        'searchTerm' => $data['searchTerm'],
                        'path' => $this->path,
                    ]))),
                ConnectSftpAction::make()
                    ->hiddenLabel()
                    ->tooltip(trans('server/file.actions.connect_sftp'))
                    ->directory($this->path),
            ]);
    }

    private function refreshPage(bool $oneBack = false): void
    {
        $url = self::getUrl(['path' => $oneBack ? dirname($this->path) : $this->path]);
        $this->redirect($url, FilamentView::hasSpaMode($url));
    }

    /**
     * @return string[]
     */
    private function getPermissionsFromModeBit(int $mode): array
    {
        return match ($mode) {
            1 => ['execute'],
            2 => ['write'],
            3 => ['write', 'execute'],
            4 => ['read'],
            5 => ['read', 'execute'],
            6 => ['read', 'write'],
            7 => ['read', 'write', 'execute'],
            default => [],
        };
    }

    #[On('server-files-uploaded')]
    public function refreshAfterUpload(): void
    {
        // Re-renders the table once the upload manager finishes a batch.
    }

    private function getDaemonFileRepository(): DaemonFileRepository
    {
        /** @var Server $server */
        $server = Filament::getTenant();
        $this->fileRepository ??= (new DaemonFileRepository())->setServer($server);

        return $this->fileRepository;
    }

    public static function route(string $path): PageRegistration
    {
        return new PageRegistration(
            page: static::class,
            route: fn (Panel $panel): Route => RouteFacade::get($path, static::class)
                ->middleware(static::getRouteMiddleware($panel))
                ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
                ->where('path', '.*'),
        );
    }

    public function fileUploadAction(): Action
    {
        if (user()?->getCustomization(CustomizationKey::ButtonStyle)) {
            return Action::make('fileUpload')
                ->iconSize(IconSize::ExtraLarge)
                ->iconButton()
                ->color('success')
                ->icon(TablerIcon::Upload)
                ->tooltip(trans('server/file.actions.upload.title'))
                ->extraAttributes(['@click' => 'triggerBrowse']);
        }

        return Action::make('fileUpload')
            ->hiddenLabel()
            ->color('success')
            ->icon(TablerIcon::Upload)
            ->tooltip(trans('server/file.actions.upload.title'))
            ->extraAttributes(['@click' => 'triggerBrowse']);
    }
}
