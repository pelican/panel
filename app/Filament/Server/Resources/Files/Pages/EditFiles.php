<?php

namespace App\Filament\Server\Resources\Files\Pages;

use App\Enums\EditorLanguages;
use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Exceptions\Http\Server\FileSizeTooLargeException;
use App\Exceptions\Repository\FileNotEditableException;
use App\Facades\Activity;
use App\Filament\Components\Forms\Fields\MonacoEditor;
use App\Filament\Server\Resources\Files\FileResource;
use App\Livewire\AlertBanner;
use App\Models\File;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Panel;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\PageRegistration;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Js;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * @property Schema $form
 */
class EditFiles extends Page
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static string $resource = FileResource::class;

    protected string $view = 'filament.server.pages.edit-file';

    protected static ?string $title = '';

    #[Locked]
    public string $path;

    public ?string $previousUrl = null;

    private DaemonFileRepository $fileRepository;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /**
     * @throws Throwable
     */
    public function form(Schema $schema): Schema
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        Activity::event('server:file.read')
            ->property('file', $this->path)
            ->log();

        return $schema
            ->components([
                Section::make(trans('server/file.actions.edit.title', ['file' => $this->path]))
                    ->footerActions([
                        Action::make('fm_save_and_close')
                            ->label(trans('server/file.actions.edit.save_close'))
                            ->authorize(fn () => user()?->can(SubuserPermission::FileUpdate, $server))
                            ->icon(TablerIcon::DeviceFloppy)
                            ->keyBindings('mod+shift+s')
                            ->action(function () {
                                $this->getDaemonFileRepository()->putContent($this->path, $this->data['editor'] ?? '');

                                Activity::event('server:file.write')
                                    ->property('file', $this->path)
                                    ->log();

                                Notification::make()
                                    ->success()
                                    ->title(trans('server/file.actions.edit.notification'))
                                    ->body(fn () => $this->path)
                                    ->send();

                                $this->redirectToList();
                            }),
                        Action::make('fm_save')
                            ->label(trans('server/file.actions.edit.save'))
                            ->authorize(fn () => user()?->can(SubuserPermission::FileUpdate, $server))
                            ->icon(TablerIcon::DeviceFloppy)
                            ->keyBindings('mod+s')
                            ->action(function () {
                                $this->getDaemonFileRepository()->putContent($this->path, $this->data['editor'] ?? '');

                                Activity::event('server:file.write')
                                    ->property('file', $this->path)
                                    ->log();

                                Notification::make()
                                    ->success()
                                    ->title(trans('server/file.actions.edit.notification'))
                                    ->body(fn () => $this->path)
                                    ->send();
                            }),
                        Action::make('fm_cancel')
                            ->label(trans('server/file.actions.edit.cancel'))
                            ->color('danger')
                            ->icon(TablerIcon::X)
                            ->alpineClickHandler(function () {
                                $url = $this->previousUrl ?? ListFiles::getUrl(['path' => dirname($this->path)]);

                                return FilamentView::hasSpaMode($url)
                                    ? 'document.referrer ? window.history.back() : Livewire.navigate(' . Js::from($url) . ')'
                                    : 'document.referrer ? window.history.back() : (window.location.href = ' . Js::from($url) . ')';
                            }),
                    ])
                    ->footerActionsAlignment(Alignment::End)
                    ->schema([
                        Select::make('lang')
                            ->label(trans('server/file.actions.new_file.syntax'))
                            ->searchable()
                            ->live()
                            ->options(EditorLanguages::class)
                            ->selectablePlaceholder(false)
                            ->afterStateUpdated(fn ($state) => $this->dispatch('setLanguage', lang: $state))
                            ->default(fn () => EditorLanguages::fromWithAlias(pathinfo($this->path, PATHINFO_EXTENSION))),
                        MonacoEditor::make('editor')
                            ->hiddenLabel()
                            ->language(fn (Get $get) => $get('lang'))
                            ->default(function () {
                                try {
                                    $contents = $this->getDaemonFileRepository()->getContent($this->path, config('panel.files.max_edit_size'));

                                    return convert_to_utf8($contents);
                                } catch (FileSizeTooLargeException) {
                                    AlertBanner::make('file_too_large')
                                        ->title(trans('server/file.alerts.file_too_large.title', ['name' => basename($this->path)]))
                                        ->body(trans('server/file.alerts.file_too_large.body', ['max' => convert_bytes_to_readable(config('panel.files.max_edit_size'))]))
                                        ->danger()
                                        ->closable()
                                        ->send();
                                } catch (FileNotFoundException) {
                                    AlertBanner::make('file_not_found')
                                        ->title(trans('server/file.alerts.file_not_found.title', ['name' => basename($this->path)]))
                                        ->danger()
                                        ->closable()
                                        ->send();
                                } catch (FileNotEditableException) {
                                    AlertBanner::make('file_is_directory')
                                        ->title(trans('server/file.alerts.file_not_found.title', ['name' => basename($this->path)]))
                                        ->danger()
                                        ->closable()
                                        ->send();
                                } catch (ConnectionException) {
                                    // Alert banner for this one will be handled by ListFiles
                                }

                                $this->redirectToList();
                            }),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private function redirectToList(): void
    {
        $url = ListFiles::getUrl(['path' => dirname($this->path)]);
        $this->redirect($url, FilamentView::hasSpaMode($url));
    }

    public function mount(string $path): void
    {
        $this->authorizeAccess();

        $this->path = $path;

        $this->form->fill();

        $this->previousUrl = url()->previous();

        foreach (File::getSpecialFiles() as $fileName => $data) {
            if ($data['check'] instanceof Closure && $data['check']($path)) {
                AlertBanner::make($fileName . '_info')
                    ->title($data['title'])
                    ->body($data['body'])
                    ->info()
                    ->closable()
                    ->send();
            }
        }
    }

    protected function authorizeAccess(): void
    {
        abort_unless(user()?->can(SubuserPermission::FileReadContent, Filament::getTenant()), 403);
    }

    /**
     * @return array<string, Schema>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(static::getResource()::form(
                $this->makeSchema()
                    ->statePath($this->getFormStatePath())
                    ->columns($this->hasInlineLabels() ? 1 : 2)
                    ->inlineLabel($this->hasInlineLabels()),
            )),
        ];
    }

    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function getBreadcrumbs(): array
    {
        $resource = static::getResource();

        $breadcrumbs = [
            $resource::getUrl() => $resource::getBreadcrumb(),
        ];

        $parts = explode('/', $this->path);
        $fileName = array_pop($parts);

        $previousParts = '';
        foreach ($parts as $part) {
            $previousParts = $previousParts . '/' . $part;
            $breadcrumbs[ListFiles::getUrl(['path' => ltrim($previousParts, '/')])] = $part;
        }

        $breadcrumbs[] = $fileName;

        return $breadcrumbs;
    }

    private function getDaemonFileRepository(): DaemonFileRepository
    {
        /** @var Server $server */
        $server = Filament::getTenant();
        $this->fileRepository ??= (new DaemonFileRepository())->setServer($server);

        return $this->fileRepository;
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false, ?string $configuration = null): string
    {
        return parent::getUrl($parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters, $configuration) . '/';
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
}
