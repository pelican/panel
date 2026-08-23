<?php

namespace App\Filament\Admin\Resources\Plugins;

use App\Enums\PluginStatus;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Jobs\Plugin\InstallPlugin;
use App\Jobs\Plugin\UninstallPlugin;
use App\Jobs\Plugin\UpdatePlugin;
use App\Models\Plugin;
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
                    ->description(fn (Plugin $plugin) => (strlen($plugin->description) > 80) ? substr($plugin->description, 0, 80).'...' : $plugin->description)
                    ->icon(fn (Plugin $plugin) => $plugin->isUpdateAvailable() ? TablerIcon::VersionsOff : TablerIcon::Versions)
                    ->iconColor(fn (Plugin $plugin) => $plugin->isUpdateAvailable() ? 'danger' : 'success')
                    ->tooltip(fn (Plugin $plugin) => $plugin->isUpdateAvailable() ? trans('admin/plugin.update_available') : null)
                    ->sortable()
                    ->searchable(),
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
                    ->tooltip(fn (Plugin $plugin) => $plugin->status_message)
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('exclude_view')
                    ->label(trans('filament-actions::view.single.label'))
                    ->icon(fn (Plugin $plugin) => $plugin->getReadme() ? TablerIcon::Eye : TablerIcon::EyeShare)
                    ->color('gray')
                    ->visible(fn (Plugin $plugin) => $plugin->getReadme() || $plugin->url)
                    ->url(fn (Plugin $plugin) => !$plugin->getReadme() ? $plugin->url : null, true)
                    ->slideOver(true)
                    ->modalHeading(trans('admin/plugin.readme'))
                    ->modalSubmitAction(fn (Plugin $plugin) => Action::make('exclude_visit_website')
                        ->label(trans('admin/plugin.visit_website'))
                        ->visible(!is_null($plugin->url))
                        ->url($plugin->url, true)
                    )
                    ->modalCancelActionLabel(trans('filament::components/modal.actions.close.label'))
                    ->schema(fn (Plugin $plugin) => $plugin->getReadme() ? [
                        TextEntry::make('readme')
                            ->hiddenLabel()
                            ->markdown()
                            ->state(fn (Plugin $plugin) => $plugin->getReadme()),
                    ] : null),
                Action::make('exclude_settings')
                    ->label(trans('admin/plugin.settings'))
                    ->authorize(fn (Plugin $plugin) => user()?->can('update', $plugin))
                    ->icon(TablerIcon::Settings)
                    ->color('primary')
                    ->visible(fn (Plugin $plugin) => $plugin->status === PluginStatus::Enabled && $plugin->hasSettings())
                    ->schema(fn (Plugin $plugin) => $plugin->getSettingsForm())
                    ->fillForm(fn (Plugin $plugin) => $plugin->getSettingsFormData())
                    ->action(fn (array $data, Plugin $plugin) => $plugin->saveSettings($data))
                    ->slideOver(),
                ActionGroup::make([
                    Action::make('exclude_install')
                        ->label(trans('admin/plugin.install'))
                        ->authorize(fn (Plugin $plugin) => user()?->can('update', $plugin))
                        ->icon(TablerIcon::Terminal)
                        ->color('success')
                        ->hidden(fn (Plugin $plugin) => $plugin->status !== PluginStatus::NotInstalled)
                        ->action(function (Plugin $plugin) {
                            try {
                                InstallPlugin::dispatch(user(), $plugin->id);

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
                        ->authorize(fn (Plugin $plugin) => user()?->can('update', $plugin))
                        ->icon(TablerIcon::Download)
                        ->color('success')
                        ->visible(fn (Plugin $plugin) => $plugin->status !== PluginStatus::NotInstalled && $plugin->isUpdateAvailable())
                        ->action(function (Plugin $plugin) {
                            try {
                                UpdatePlugin::dispatch(user(), $plugin->id);

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
                        ->authorize(fn (Plugin $plugin) => user()?->can('update', $plugin))
                        ->icon(TablerIcon::Check)
                        ->color('success')
                        ->visible(fn (Plugin $plugin) => $plugin->canEnable())
                        ->requiresConfirmation(fn (Plugin $plugin, PluginService $pluginService) => $plugin->isTheme() && $pluginService->hasThemePluginEnabled())
                        ->modalHeading(fn (Plugin $plugin, PluginService $pluginService) => $plugin->isTheme() && $pluginService->hasThemePluginEnabled() ? trans('admin/plugin.enable_theme_modal.heading') : null)
                        ->modalDescription(fn (Plugin $plugin, PluginService $pluginService) => $plugin->isTheme() && $pluginService->hasThemePluginEnabled() ? trans('admin/plugin.enable_theme_modal.description') : null)
                        ->action(function (Plugin $plugin, $livewire, PluginService $pluginService) {
                            $pluginService->enablePlugin($plugin);

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.enabled'))
                                ->send();
                        }),
                    Action::make('exclude_disable')
                        ->label(trans('admin/plugin.disable'))
                        ->authorize(fn (Plugin $plugin) => user()?->can('update', $plugin))
                        ->icon(TablerIcon::X)
                        ->color('warning')
                        ->visible(fn (Plugin $plugin) => $plugin->canDisable())
                        ->action(function (Plugin $plugin, $livewire, PluginService $pluginService) {
                            $pluginService->disablePlugin($plugin);

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.disabled'))
                                ->send();
                        }),
                    Action::make('exclude_delete')
                        ->label(trans('filament-actions::delete.single.label'))
                        ->authorize(fn (Plugin $plugin) => user()?->can('delete', $plugin))
                        ->icon(TablerIcon::Trash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Plugin $plugin) => $plugin->status === PluginStatus::NotInstalled || $plugin->status === PluginStatus::Errored)
                        ->action(function (Plugin $plugin, $livewire, PluginService $pluginService) {
                            $pluginService->deletePlugin($plugin);

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.deleted'))
                                ->send();
                        }),
                    Action::make('exclude_uninstall')
                        ->label(trans('admin/plugin.uninstall'))
                        ->authorize(fn (Plugin $plugin) => user()?->can('update', $plugin))
                        ->icon(TablerIcon::Terminal)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->hidden(fn (Plugin $plugin) => $plugin->status === PluginStatus::NotInstalled || $plugin->status === PluginStatus::Errored)
                        ->action(function (Plugin $plugin) {
                            try {
                                UninstallPlugin::dispatch(user(), $plugin->id);

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

                            $pluginService->downloadPluginFromFile($file);

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
                            $pluginService->downloadPluginFromUrl($data['url']);

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
