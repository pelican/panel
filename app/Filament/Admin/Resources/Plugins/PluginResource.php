<?php

namespace App\Filament\Admin\Resources\Plugins;

use App\Enums\PluginStatus;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Jobs\Plugin\InstallPlugin;
use App\Jobs\Plugin\UninstallPlugin;
use App\Jobs\Plugin\UpdatePlugin;
use App\Models\Plugin;
use App\Models\WingsPlugin;
use App\Services\Helpers\PluginService;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;

class PluginResource extends Resource
{
    protected static ?string $model = Plugin::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Packages;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return trans('admin/plugin.nav_title');
    }

    public static function getModelLabel(): string
    {
        return trans('admin/plugin.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('admin/plugin.model_label_plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    /**
     * Run an action against the node a Wings plugin lives on and report what
     * came back.
     *
     * Every Wings action is a call to another machine, which can fail for
     * reasons the Panel cannot see, so nothing here reports a success the node
     * did not confirm. The cached rows are dropped either way, so the table
     * shows the node's real state rather than what it said beforehand.
     */
    private static function runOnNode(WingsPlugin $record, callable $callback, string $errorKey, string $successKey): void
    {
        try {
            $callback();
        } catch (Exception $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(trans("admin/plugin.notifications.$errorKey"))
                ->body($exception->getMessage())
                ->send();

            return;
        } finally {
            WingsPlugin::flush();
        }

        Notification::make()
            ->success()
            ->title(trans("admin/plugin.notifications.$successKey"))
            ->send();

        redirect(ListPlugins::getUrl(['tab' => 'wings']));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->openRecordUrlInNewTab()
            ->reorderable('load_order')
            ->authorizeReorder(fn () => user()?->can('update plugin'))
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering) => $action->hiddenLabel()->tooltip($isReordering ? trans('admin/plugin.apply_load_order') : trans('admin/plugin.change_load_order')))
            ->defaultSort('load_order')
            ->columns([
                TextColumn::make('name')
                    ->label(trans('admin/plugin.name'))
                    ->description(fn (Plugin $record) => (strlen($record->description) > 80) ? substr($record->description, 0, 80).'...' : $record->description)
                    ->icon(fn (Plugin $record) => $record->isUpdateAvailable() ? TablerIcon::VersionsOff : TablerIcon::Versions)
                    ->iconColor(fn (Plugin $record) => $record->isUpdateAvailable() ? 'danger' : 'success')
                    ->tooltip(fn (Plugin $record) => $record->isUpdateAvailable() ? trans('admin/plugin.update_available') : null)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('node_name')
                    ->label(trans('admin/plugin.node'))
                    ->badge()
                    ->sortable()
                    ->visible(fn ($livewire) => $livewire->activeTab === 'wings'),
                TextColumn::make('author')
                    ->label(trans('admin/plugin.author'))
                    ->sortable(),
                TextColumn::make('version')
                    ->label(trans('admin/plugin.version'))
                    ->sortable(),
                TextColumn::make('category')
                    ->label(trans('admin/plugin.category'))
                    ->badge()
                    ->sortable()
                    ->visible(fn ($livewire) => $livewire->activeTab === 'all'),
                TextColumn::make('status')
                    ->label(trans('admin/plugin.status'))
                    ->badge()
                    ->tooltip(fn (Plugin $record) => $record->status_message)
                    ->description(fn (Plugin $record) => is_null($record->api_version) ? trans('admin/plugin.api_version_missing') : null)
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('exclude_view')
                    ->label(trans('filament-actions::view.single.label'))
                    ->icon(fn (Plugin $record) => $record->getReadme() ? TablerIcon::Eye : TablerIcon::EyeShare)
                    ->color('gray')
                    ->visible(fn (Plugin $record) => $record->getReadme() || $record->url)
                    ->url(fn (Plugin $record) => !$record->getReadme() ? $record->url : null, true)
                    ->slideOver(true)
                    ->modalHeading(trans('admin/plugin.readme'))
                    ->modalSubmitAction(fn (Plugin $record) => Action::make('exclude_visit_website')
                        ->label(trans('admin/plugin.visit_website'))
                        ->visible(!is_null($record->url))
                        ->url($record->url, true)
                    )
                    ->modalCancelActionLabel(trans('filament::components/modal.actions.close.label'))
                    ->schema(fn (Plugin $record) => $record->getReadme() ? [
                        TextEntry::make('readme')
                            ->hiddenLabel()
                            ->markdown()
                            ->state(fn (Plugin $record) => $record->getReadme()),
                    ] : null),
                Action::make('exclude_settings')
                    ->label(trans('admin/plugin.settings'))
                    ->authorize(fn (Plugin $record) => user()?->can('update', $record))
                    ->icon(TablerIcon::Settings)
                    ->color('primary')
                    ->visible(fn (Plugin $record) => $record->status === PluginStatus::Enabled && $record->hasSettings())
                    ->schema(fn (Plugin $record) => $record->getSettingsForm())
                    ->fillForm(fn (Plugin $record) => $record->getSettingsFormData())
                    ->action(fn (array $data, Plugin $record) => $record->saveSettings($data))
                    ->slideOver(),
                ActionGroup::make([
                    Action::make('exclude_install')
                        ->label(trans('admin/plugin.install'))
                        ->authorize(fn (Plugin $record) => user()?->can('update', $record))
                        ->icon(TablerIcon::Terminal)
                        ->color('success')
                        ->hidden(fn (Plugin $record) => $record->status !== PluginStatus::NotInstalled)
                        ->action(function (Plugin $record) {
                            // A Wings plugin is installed by the node, not by a
                            // job here: the files are already there and it is
                            // the daemon that has to prove they load.
                            if ($record instanceof WingsPlugin) {
                                self::runOnNode($record, fn () => $record->repository()->install($record->plugin_id), 'install_error', 'enabled');

                                return;
                            }

                            try {
                                InstallPlugin::dispatch(user(), $record->id);

                                Notification::make()
                                    ->success()
                                    ->title(trans('admin/plugin.notifications.install_started'))
                                    ->body(trans('admin/plugin.notifications.background_info'))
                                    ->send();
                            } catch (Exception $exception) {
                                Notification::make()
                                    ->danger()
                                    ->title(trans('admin/plugin.notifications.install_error'))
                                    ->body($exception->getMessage())
                                    ->send();
                            }
                        }),
                    Action::make('exclude_update')
                        ->label(trans('admin/plugin.update'))
                        ->authorize(fn (Plugin $record) => user()?->can('update', $record))
                        ->icon(TablerIcon::Download)
                        ->color('success')
                        ->visible(fn (Plugin $record) => $record->isUpdateAvailable())
                        ->action(function (Plugin $record) {
                            try {
                                UpdatePlugin::dispatch(user(), $record->id);

                                Notification::make()
                                    ->success()
                                    ->title(trans('admin/plugin.notifications.update_started'))
                                    ->body(trans('admin/plugin.notifications.background_info'))
                                    ->send();
                            } catch (Exception $exception) {
                                Notification::make()
                                    ->danger()
                                    ->title(trans('admin/plugin.notifications.update_error'))
                                    ->body($exception->getMessage())
                                    ->send();
                            }
                        }),
                    Action::make('exclude_enable')
                        ->label(trans('admin/plugin.enable'))
                        ->authorize(fn (Plugin $record) => user()?->can('update', $record))
                        ->icon(TablerIcon::Check)
                        ->color('success')
                        ->visible(fn (Plugin $record) => $record->canEnable())
                        ->requiresConfirmation(fn (Plugin $record, PluginService $pluginService) => $record->isTheme() && $pluginService->hasThemePluginEnabled())
                        ->modalHeading(fn (Plugin $record, PluginService $pluginService) => $record->isTheme() && $pluginService->hasThemePluginEnabled() ? trans('admin/plugin.enable_theme_modal.heading') : null)
                        ->modalDescription(fn (Plugin $record, PluginService $pluginService) => $record->isTheme() && $pluginService->hasThemePluginEnabled() ? trans('admin/plugin.enable_theme_modal.description') : null)
                        ->action(function (Plugin $record, $livewire, PluginService $pluginService) {
                            if ($record instanceof WingsPlugin) {
                                self::runOnNode($record, fn () => $record->repository()->enable($record->plugin_id), 'enable_error', 'enabled');

                                return;
                            }

                            $pluginService->enablePlugin($record);

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.enabled'))
                                ->send();
                        }),
                    Action::make('exclude_disable')
                        ->label(trans('admin/plugin.disable'))
                        ->authorize(fn (Plugin $record) => user()?->can('update', $record))
                        ->icon(TablerIcon::X)
                        ->color('warning')
                        ->visible(fn (Plugin $record) => $record->canDisable())
                        ->action(function (Plugin $record, $livewire, PluginService $pluginService) {
                            if ($record instanceof WingsPlugin) {
                                self::runOnNode($record, fn () => $record->repository()->disable($record->plugin_id), 'disable_error', 'disabled');

                                return;
                            }

                            $pluginService->disablePlugin($record);

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.disabled'))
                                ->send();
                        }),
                    Action::make('exclude_delete')
                        ->label(trans('filament-actions::delete.single.label'))
                        ->authorize(fn (Plugin $record) => user()?->can('delete', $record))
                        ->icon(TablerIcon::Trash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Plugin $record) => $record->status === PluginStatus::NotInstalled || $record->status === PluginStatus::Errored)
                        ->action(function (Plugin $record, $livewire, PluginService $pluginService) {
                            if ($record instanceof WingsPlugin) {
                                self::runOnNode($record, fn () => $record->repository()->uninstall($record->plugin_id, true), 'uninstall_error', 'deleted');

                                return;
                            }

                            $pluginService->deletePlugin($record);

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.deleted'))
                                ->send();
                        }),
                    Action::make('exclude_uninstall')
                        ->label(trans('admin/plugin.uninstall'))
                        ->authorize(fn (Plugin $record) => user()?->can('update', $record))
                        ->icon(TablerIcon::Terminal)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->hidden(fn (Plugin $record) => $record->status === PluginStatus::NotInstalled || $record->status === PluginStatus::Errored)
                        ->action(function (Plugin $record) {
                            if ($record instanceof WingsPlugin) {
                                self::runOnNode($record, fn () => $record->repository()->uninstall($record->plugin_id), 'uninstall_error', 'uninstalled');

                                return;
                            }

                            try {
                                UninstallPlugin::dispatch(user(), $record->id);

                                Notification::make()
                                    ->success()
                                    ->title(trans('admin/plugin.notifications.uninstall_started'))
                                    ->body(trans('admin/plugin.notifications.background_info'))
                                    ->send();
                            } catch (Exception $exception) {
                                Notification::make()
                                    ->danger()
                                    ->title(trans('admin/plugin.notifications.uninstall_error'))
                                    ->body($exception->getMessage())
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->headerActions([
                Action::make('import_from_file')
                    ->label(trans('admin/plugin.import_from_file'))
                    ->modalHeading(trans('admin/plugin.import_from_file'))
                    ->hiddenLabel()
                    ->tooltip(trans('admin/plugin.import_from_file'))
                    ->authorize(fn () => user()?->can('create', Plugin::class))
                    ->icon(TablerIcon::FileDownload)
                    ->schema([
                        // TODO: switch to new file upload
                        FileUpload::make('file')
                            ->label(trans('admin/plugin.file'))
                            ->required()
                            ->acceptedFileTypes(['application/zip', 'application/zip-compressed', 'application/x-zip-compressed'])
                            ->preserveFilenames()
                            ->previewable(false)
                            ->storeFiles(false),
                    ])
                    ->action(function ($data, $livewire, PluginService $pluginService) {
                        try {
                            /** @var UploadedFile $file */
                            $file = $data['file'];

                            $id = $pluginService->downloadPluginFromFile($file);

                            // Importing over an installed plugin is an update: reinstall it in the background.
                            if ($pluginService->isInstalled($id)) {
                                InstallPlugin::dispatch(user(), $id, reinstall: true);
                            }

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.imported'))
                                ->send();

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));
                        } catch (Exception $exception) {
                            report($exception);

                            Notification::make()
                                ->danger()
                                ->title(trans('admin/plugin.notifications.import_failed'))
                                ->body($exception->getMessage())
                                ->send();
                        }
                    }),
                Action::make('import_from_url')
                    ->label(trans('admin/plugin.import_from_url'))
                    ->modalHeading(trans('admin/plugin.import_from_url'))
                    ->hiddenLabel()
                    ->tooltip(trans('admin/plugin.import_from_url'))
                    ->authorize(fn () => user()?->can('create', Plugin::class))
                    ->icon(TablerIcon::WorldDownload)
                    ->schema([
                        TextInput::make('url')
                            ->required()
                            ->url()
                            ->endsWith('.zip'),
                    ])
                    ->action(function ($data, $livewire, PluginService $pluginService) {
                        try {
                            $id = $pluginService->downloadPluginFromUrl($data['url']);

                            // Importing over an installed plugin is an update: reinstall it in the background.
                            if ($pluginService->isInstalled($id)) {
                                InstallPlugin::dispatch(user(), $id, reinstall: true);
                            }

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.imported'))
                                ->send();

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));
                        } catch (Exception $exception) {
                            report($exception);

                            Notification::make()
                                ->danger()
                                ->title(trans('admin/plugin.notifications.import_failed'))
                                ->body($exception->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->emptyStateIcon(TablerIcon::Packages)
            ->emptyStateDescription('')
            ->emptyStateHeading(trans('admin/plugin.no_plugins'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlugins::route('/'),
        ];
    }
}
