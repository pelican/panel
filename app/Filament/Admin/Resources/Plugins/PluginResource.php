<?php

namespace App\Filament\Admin\Resources\Plugins;

use App\Enums\PluginStatus;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Plugins\Pages\BrowsePlugins;
use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Jobs\Plugin\InstallPlugin;
use App\Jobs\Plugin\UninstallPlugin;
use App\Jobs\Plugin\UpdatePlugin;
use App\Models\Plugin;
use App\Services\Helpers\PluginRegistryService;
use App\Services\Helpers\PluginService;
use App\Services\Helpers\RegistryPlugin;
use App\Services\Helpers\ThemeService;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use RuntimeException;

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
                    ->description(fn (Plugin $plugin) => is_null($plugin->api_version) ? trans('admin/plugin.api_version_missing') : null)
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
                        ->visible(fn (Plugin $plugin) => $plugin->isUpdateAvailable())
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
                        ->requiresConfirmation(fn (Plugin $plugin, PluginService $pluginService) => $plugin->isTheme() && !$plugin->isSwitchableTheme() && $pluginService->hasAnyThemeEnabled())
                        ->modalHidden(fn (Plugin $plugin, PluginService $pluginService) => $plugin->isSwitchableTheme() ? !user()?->can('update settings') : !($plugin->isTheme() && $pluginService->hasAnyThemeEnabled()))
                        ->modalHeading(fn (Plugin $plugin) => $plugin->isSwitchableTheme() ? trans('admin/plugin.default_theme_modal.heading') : trans('admin/plugin.enable_theme_modal.heading'))
                        ->modalDescription(fn (Plugin $plugin) => $plugin->isSwitchableTheme() ? trans('admin/plugin.default_theme_modal.description') : trans('admin/plugin.enable_theme_modal.description'))
                        ->schema(fn (Plugin $plugin) => $plugin->isSwitchableTheme() && user()?->can('update settings') ? static::defaultThemeSchema() : null)
                        ->action(function (Plugin $plugin, array $data, $livewire, PluginService $pluginService, ThemeService $themeService) {
                            $pluginService->enablePlugin($plugin);

                            if (($data['set_as_default'] ?? false) && user()?->can('update settings')) {
                                $themeService->setDefaultTheme($plugin->id, (bool) ($data['force_theme'] ?? false));
                            }

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.enabled'))
                                ->send();
                        }),
                    Action::make('exclude_set_default_theme')
                        ->label(trans('admin/plugin.set_default_theme'))
                        ->authorize(fn (Plugin $plugin) => user()?->can('update', $plugin) && user()->can('update settings'))
                        ->icon(TablerIcon::Palette)
                        ->visible(fn (Plugin $plugin, ThemeService $themeService) => $plugin->isSwitchableTheme() && $plugin->status === PluginStatus::Enabled && $themeService->getDefaultThemeId() !== $plugin->id)
                        ->modalHeading(trans('admin/plugin.default_theme_modal.heading'))
                        ->schema([
                            Toggle::make('force_theme')
                                ->label(trans('admin/plugin.default_theme_modal.force_theme'))
                                ->helperText(trans('admin/plugin.default_theme_modal.force_theme_help'))
                                ->default(fn (ThemeService $themeService) => $themeService->isForced()),
                        ])
                        ->action(function (Plugin $plugin, array $data, $livewire, ThemeService $themeService) {
                            $themeService->setDefaultTheme($plugin->id, (bool) ($data['force_theme'] ?? false));

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.default_theme_set'))
                                ->body($plugin->name)
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
                Action::make('browse_hub')
                    ->label(trans('admin/plugin.hub.browse'))
                    ->hiddenLabel()
                    ->tooltip(trans('admin/plugin.hub.browse'))
                    ->authorize(fn () => user()?->can('create', Plugin::class))
                    ->icon(TablerIcon::BuildingStore)
                    ->url(BrowsePlugins::getUrl()),
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
                Action::make('install_from_registry')
                    ->label(trans('admin/plugin.registry.install'))
                    ->modalHeading(trans('admin/plugin.registry.install'))
                    ->hiddenLabel()
                    ->tooltip(trans('admin/plugin.registry.install'))
                    ->authorize(fn () => user()?->can('create', Plugin::class))
                    // Without registries the plugins page stays exactly as it was.
                    ->visible(fn (PluginRegistryService $registries) => $registries->hasRegistries())
                    ->icon(TablerIcon::PackageImport)
                    ->steps([
                        Step::make('reference')
                            ->label(trans('admin/plugin.registry.reference'))
                            ->schema([
                                TextInput::make('reference')
                                    ->label(trans('admin/plugin.registry.reference'))
                                    ->helperText(trans('admin/plugin.registry.reference_help'))
                                    ->placeholder('@namespace/name')
                                    ->required()
                                    ->maxLength(2048),
                                Hidden::make('approved'),
                            ])
                            ->afterValidation(function (Get $get, Set $set, PluginRegistryService $registries) {
                                try {
                                    $set('approved', self::approval($registries->resolve((string) $get('reference'))));
                                } catch (Exception $exception) {
                                    Notification::make()
                                        ->danger()
                                        ->title(trans('admin/plugin.notifications.install_error'))
                                        ->body($exception->getMessage())
                                        ->send();

                                    throw new Halt();
                                }
                            }),
                        Step::make('review')
                            ->label(trans('admin/plugin.registry.review'))
                            ->description(trans('admin/plugin.registry.review_description'))
                            ->schema(self::registryReviewSchema()),
                    ])
                    ->action(function (array $data, $livewire, PluginService $pluginService, PluginRegistryService $registries) {
                        try {
                            // Resolved again here, from the server's own config: the review step only says what the admin approved.
                            $plugin = $registries->resolve((string) $data['reference']);

                            if (($data['approved'] ?? null) !== self::approval($plugin)) {
                                throw new RuntimeException(trans('admin/plugin.registry.changed'));
                            }

                            Plugin::refreshRows();
                            $existing = Plugin::find($plugin->name);

                            // Replacing an installed plugin's files needs permission to update it, as on the Hub page.
                            if ($existing !== null && !user()?->can('update', $existing)) {
                                throw new RuntimeException(trans('admin/plugin.hub.no_permission'));
                            }

                            $wasInstalled = $existing !== null && $existing->status !== PluginStatus::NotInstalled;

                            $id = $pluginService->downloadPluginFromRegistry($plugin);

                            Plugin::refreshRows();

                            InstallPlugin::dispatch(user(), $id, reinstall: $wasInstalled);

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.install_started'))
                                ->body(trans('admin/plugin.notifications.background_info'))
                                ->send();

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));
                        } catch (Exception $exception) {
                            report($exception);

                            Notification::make()
                                ->danger()
                                ->title(trans('admin/plugin.notifications.install_error'))
                                ->body($exception->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->emptyStateIcon(TablerIcon::Packages)
            ->emptyStateDescription('')
            ->emptyStateHeading(trans('admin/plugin.no_plugins'));
    }

    /**
     * What the admin reviews before a registry install, compared with a fresh resolve when they confirm.
     *
     * @return array<string, string|null>
     */
    protected static function approval(RegistryPlugin $plugin): array
    {
        return [
            'name' => $plugin->name,
            'version' => $plugin->version,
            'author' => $plugin->author,
            'description' => $plugin->description,
            'source' => $plugin->source(),
            'download' => $plugin->download,
            'sha256' => $plugin->sha256,
            'signed' => $plugin->signature !== null ? 'yes' : 'no',
            'panel_version' => $plugin->panelVersion,
        ];
    }

    /** @return Component[] */
    protected static function registryReviewSchema(): array
    {
        $entry = fn (string $key) => TextEntry::make("approved_$key")
            ->label(trans("admin/plugin.registry.$key"))
            ->state(fn (Get $get) => $get("approved.$key") ?? '-');

        return [
            $entry('name'),
            $entry('version'),
            $entry('author'),
            $entry('description'),
            $entry('source'),
            $entry('download'),
            $entry('sha256'),
            TextEntry::make('approved_signed')
                ->label(trans('admin/plugin.registry.signature'))
                ->state(fn (Get $get) => trans($get('approved.signed') === 'yes' ? 'admin/plugin.registry.signed' : 'admin/plugin.registry.unsigned')),
            $entry('panel_version')
                ->visible(fn (Get $get) => filled($get('approved.panel_version'))),
            TextEntry::make('approved_replaces')
                ->label(trans('admin/plugin.registry.replaces'))
                ->state(fn (Get $get) => Plugin::find((string) $get('approved.name'))?->version)
                ->visible(fn (Get $get) => Plugin::find((string) $get('approved.name')) !== null),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlugins::route('/'),
            'browse' => BrowsePlugins::route('/browse'),
        ];
    }

    /** @return Component[] */
    protected static function defaultThemeSchema(): array
    {
        return [
            Toggle::make('set_as_default')
                ->label(trans('admin/plugin.default_theme_modal.set_as_default'))
                ->live(),
            Toggle::make('force_theme')
                ->label(trans('admin/plugin.default_theme_modal.force_theme'))
                ->helperText(trans('admin/plugin.default_theme_modal.force_theme_help'))
                ->visible(fn (Get $get) => (bool) $get('set_as_default'))
                ->default(fn (ThemeService $themeService) => $themeService->isForced()),
        ];
    }
}
