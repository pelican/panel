<?php

namespace App\Filament\Admin\Resources\Plugins;

use App\Enums\PluginStatus;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Plugins\Pages\BrowsePlugins;
use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Jobs\Plugin\InstallPlugin;
use App\Jobs\Plugin\UninstallPlugin;
use App\Jobs\Plugin\UpdatePlugin;
use App\Models\Node;
use App\Models\Plugin;
use App\Models\WingsPlugin;
use App\Repositories\Daemon\DaemonPluginRepository;
use App\Services\Helpers\PluginService;
use App\Services\Helpers\ThemeService;
use App\Services\Nodes\WingsPluginApprovals;
use App\Services\Nodes\WingsPluginFailure;
use BackedEnum;
use Closure;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

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
     * Run an action on a node and only report success if the node confirmed it.
     */
    private static function runOnNode(callable $callback, string $errorKey, string $successKey): void
    {
        try {
            $callback();
        } catch (Exception $exception) {
            // An answer from the node (403, 409, ...) is not a Panel error
            if (!$exception instanceof RequestException) {
                report($exception);
            }

            Notification::make()
                ->danger()
                ->title(trans("admin/plugin.notifications.$errorKey"))
                ->body(WingsPluginFailure::message($exception))
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

    /**
     * First step of a two-step install, update or import (contract v3, A4): Wings stages the plugin and
     * nothing is installed. What it staged is kept on the server and the admin is asked to approve it.
     * $source is what the stage is made from, so it can be staged again (null: it can't be).
     *
     * @param  array<string, string|null>|null  $source
     */
    public static function stageOnNode(ListPlugins $livewire, WingsPluginApprovals $approvals, Node $node, string $action, ?string $pluginId, ?array $source): void
    {
        self::stage($livewire, $approvals, $node, $action, $pluginId, $source, function (DaemonPluginRepository $repository) use ($action, $pluginId, $source): array {
            $source ??= [];

            return match ($action) {
                'install' => $repository->install((string) $pluginId, $source['registry'] ?? null),
                'update' => $repository->update((string) $pluginId),
                'import' => $repository->importFromUrl((string) ($source['url'] ?? ''), $source['sha256'] ?? null, $source['signature'] ?? null),
                default => throw new InvalidArgumentException("Unknown plugin action \"$action\"."),
            };
        });
    }

    /**
     * @param  array<string, string|null>|null  $source
     * @param  Closure(DaemonPluginRepository): array<string, mixed>  $stage
     */
    private static function stage(ListPlugins $livewire, WingsPluginApprovals $approvals, Node $node, string $action, ?string $pluginId, ?array $source, Closure $stage): void
    {
        $errorKey = $action === 'import' ? 'import_failed' : "{$action}_error";

        try {
            $staged = $stage((new DaemonPluginRepository())->setNode($node));

            if ($staged['status'] === 'up_to_date') {
                Notification::make()
                    ->info()
                    ->title(trans('admin/plugin.wings.up_to_date', [
                        'name' => $pluginId,
                        'installed' => is_string($staged['installed'] ?? null) ? $staged['installed'] : '?',
                        'latest' => is_string($staged['latest'] ?? null) ? $staged['latest'] : '?',
                    ]))
                    ->send();

                return;
            }

            if ($staged['status'] !== 'approval_required') {
                throw new InvalidArgumentException(trans('admin/plugin.wings.unexpected_answer'));
            }

            $handle = $approvals->put(user() ?? throw new RuntimeException('Not signed in.'), $node, $action, $pluginId, $staged, $source);
        } catch (Exception $exception) {
            if (!$exception instanceof RequestException) {
                report($exception);
            }

            Notification::make()
                ->danger()
                ->title(trans("admin/plugin.notifications.$errorKey"))
                ->body(WingsPluginFailure::message($exception))
                ->send();

            return;
        }

        $livewire->replaceMountedAction('approveWingsPlugin', ['approval' => $handle]);
    }

    /**
     * The node the admin picked, if it is one they can access and it allows $level (contract A7, L).
     */
    public static function nodeAllowing(mixed $nodeId, string $level): ?Node
    {
        $nodeId = filter_var($nodeId, FILTER_VALIDATE_INT);
        if ($nodeId === false || !WingsPlugin::nodesAllowing($level)->has($nodeId)) {
            return null;
        }

        $node = Node::find($nodeId);

        return $node && user()?->canTarget($node) ? $node : null;
    }

    /**
     * The plugins the node's registries offer, keyed "@namespace/name"; nothing for a node the admin can't
     * use at "signed".
     *
     * @return array<string, string>
     */
    private static function availableOptions(mixed $nodeId): array
    {
        $node = self::nodeAllowing($nodeId, 'signed');
        if (!$node) {
            return [];
        }

        $options = [];
        foreach (WingsPlugin::availableOn($node)['data'] as $entry) {
            $notes = array_filter([
                $entry['has_signature'] ? trans('admin/plugin.wings.available_signed') : null,
                $entry['installed'] !== null ? trans('admin/plugin.wings.available_installed', ['version' => $entry['installed']]) : null,
            ]);

            $label = trim("{$entry['name']} {$entry['version']}") . " (@{$entry['registry']})";
            $options["@{$entry['registry']}/{$entry['name']}"] = $notes === [] ? $label : $label . ' · ' . implode(' · ', $notes);
        }

        return $options;
    }

    /** Whether the option is a plugin the node already has: installing it again is a conflict (A1 409). */
    private static function availableInstalled(mixed $nodeId, string $key): bool
    {
        $node = self::nodeAllowing($nodeId, 'signed');
        $picked = self::parseAvailableKey($key);

        return $node && $picked && collect(WingsPlugin::availableOn($node)['data'])
            ->contains(fn (array $entry) => $entry['registry'] === $picked[0] && $entry['name'] === $picked[1] && $entry['installed'] !== null);
    }

    /** Registries the node couldn't read, or why it couldn't list them at all; the form still works. */
    private static function availableProblems(mixed $nodeId): ?string
    {
        $node = self::nodeAllowing($nodeId, 'signed');
        if (!$node) {
            return null;
        }

        $available = WingsPlugin::availableOn($node);
        if ($available['failure'] !== null) {
            return $available['failure'];
        }

        return $available['errors'] === [] ? null : collect($available['errors'])
            ->map(fn (string $message, string $namespace) => "@$namespace: $message")
            ->implode("\n");
    }

    /** @return array{0: string, 1: string}|null [namespace, name] of an "@namespace/name" option */
    private static function parseAvailableKey(mixed $key): ?array
    {
        if (!is_string($key) || !preg_match('#^@([^/]+)/(.+)$#', $key, $matches)) {
            return null;
        }

        if (!preg_match(DaemonPluginRepository::REGISTRY_PATTERN, $matches[1]) || !DaemonPluginRepository::isValidId($matches[2])) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }

    /**
     * @param  array{installed: ?string, latest: ?string, available: bool, error: ?string}|Throwable|null  $result
     */
    private static function notifyUpdateCheck(WingsPlugin $plugin, array|Throwable|null $result): void
    {
        if (!is_array($result)) {
            if ($result instanceof Throwable && !$result instanceof RequestException) {
                report($result);
            }

            Notification::make()
                ->danger()
                ->title(trans('admin/plugin.wings.update_check_failed', ['name' => $plugin->name]))
                ->body($result instanceof Throwable ? WingsPluginFailure::message($result) : null)
                ->send();

            return;
        }

        $values = [
            'name' => $plugin->name,
            'installed' => $result['installed'] ?? $plugin->version,
            'latest' => $result['latest'] ?? '?',
        ];

        if ($result['error'] !== null) {
            Notification::make()
                ->warning()
                ->title(trans('admin/plugin.wings.update_check_failed', ['name' => $plugin->name]))
                ->body($result['error'])
                ->send();
        } elseif ($result['available']) {
            Notification::make()
                ->success()
                ->title(trans('admin/plugin.wings.update_found', $values))
                ->body($plugin->canUpdate() ? trans('admin/plugin.wings.update_found_can') : trans('admin/plugin.wings.update_found_cannot'))
                ->send();
        } else {
            Notification::make()
                ->info()
                ->title(trans('admin/plugin.wings.update_none', $values))
                ->send();
        }
    }

    private static function notifyNodeNotAllowed(string $errorKey): void
    {
        Notification::make()
            ->danger()
            ->title(trans("admin/plugin.notifications.$errorKey"))
            ->body(trans('admin/plugin.wings.node_not_allowed'))
            ->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->openRecordUrlInNewTab()
            // Wings plugins have no load order: each is its own process (contract A6)
            ->reorderable('load_order', fn ($livewire) => $livewire->activeTab !== 'wings')
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
                // How much the Panel may do on the node; only its operator can change it (contract L)
                TextColumn::make('panel_access')
                    ->label(trans('admin/plugin.wings.panel_access'))
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'full' => 'danger',
                        'signed' => 'warning',
                        'view' => 'success',
                        default => 'gray',
                    })
                    ->placeholder('-')
                    ->visible(fn ($livewire) => $livewire->activeTab === 'wings'),
                // Source or binary, and the OS user it runs as (contract v3.2, S); unknown on an older Wings
                TextColumn::make('form')
                    ->label(trans('admin/plugin.wings.form'))
                    ->badge()
                    ->state(fn (Plugin $record) => $record instanceof WingsPlugin && $record->isPlugin() ? ($record->form ?? 'unknown') : null)
                    ->formatStateUsing(fn (string $state) => trans("admin/plugin.wings.form_$state"))
                    ->color(fn (?string $state) => match ($state) {
                        'source' => 'success',
                        'binary' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (Plugin $record) => $record instanceof WingsPlugin && $record->binaryFileList() !== []
                        ? trans('admin/plugin.wings.binary_files') . ': ' . implode(', ', $record->binaryFileList())
                        : null)
                    ->placeholder('-')
                    ->visible(fn ($livewire) => $livewire->activeTab === 'wings'),
                TextColumn::make('runs_as')
                    ->label(trans('admin/plugin.wings.runs_as'))
                    ->state(fn (Plugin $record) => $record instanceof WingsPlugin && $record->isPlugin() ? ($record->runs_as ?? trans('admin/plugin.wings.form_unknown')) : null)
                    ->color(fn (Plugin $record) => $record instanceof WingsPlugin && $record->runs_as === 'root' ? 'danger' : null)
                    ->placeholder('-')
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
                    // Wings says which plugins can be configured; a disabled one can be too (contract A3)
                    ->visible(fn (Plugin $record) => $record instanceof WingsPlugin ? $record->canConfigure() : $record->status === PluginStatus::Enabled && $record->hasSettings())
                    ->schema(fn (Plugin $record) => $record->getSettingsForm())
                    ->fillForm(fn (Plugin $record) => $record->getSettingsFormData())
                    ->action(function (array $data, Plugin $record, Schema $schema) {
                        try {
                            $record->saveSettings($data);
                        } catch (Exception $exception) {
                            // Settings Wings refused are shown on their fields (A3.1). A key that is no field
                            // (a read-only one) would be read as a nested path, so then they all go to the notification
                            $fields = WingsPluginFailure::fieldErrors($exception);
                            if ($fields !== [] && collect($fields)->keys()->every(fn (string $key) => WingsPlugin::isValidSettingKey($key))) {
                                throw ValidationException::withMessages(collect($fields)
                                    ->mapWithKeys(fn (string $message, string $key) => [$schema->getStatePath() . '.' . $key => $message])
                                    ->all());
                            }

                            if (!$exception instanceof RequestException) {
                                report($exception);
                            }

                            Notification::make()
                                ->danger()
                                ->title(trans('admin/plugin.notifications.settings_error'))
                                ->body(WingsPluginFailure::message($exception))
                                ->send();

                            return;
                        }

                        if ($record instanceof WingsPlugin) {
                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.settings_saved'))
                                ->send();
                        }
                    })
                    ->slideOver(),
                ActionGroup::make([
                    Action::make('exclude_install')
                        ->label(trans('admin/plugin.install'))
                        ->authorize(fn (Plugin $record) => user()?->can('update', $record))
                        ->icon(TablerIcon::Terminal)
                        ->color('success')
                        ->hidden(fn (Plugin $record) => $record instanceof WingsPlugin || $record->status !== PluginStatus::NotInstalled)
                        ->action(function (Plugin $record) {
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
                        ->visible(fn (Plugin $record) => $record instanceof WingsPlugin ? $record->canUpdate() : $record->isUpdateAvailable())
                        ->action(function (Plugin $record, ListPlugins $livewire, WingsPluginApprovals $approvals) {
                            if ($record instanceof WingsPlugin) {
                                try {
                                    $repository = $record->repository();
                                } catch (RuntimeException) {
                                    self::notifyNodeNotAllowed('update_error');

                                    return;
                                }

                                self::stage($livewire, $approvals, $record->node() ?? throw new RuntimeException('The node no longer exists.'), 'update', $record->plugin_id, [], fn () => $repository->update($record->plugin_id));

                                return;
                            }

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
                    // Whether a newer version exists; needs only "view" (A2 "update-check")
                    Action::make('exclude_check_update')
                        ->label(trans('admin/plugin.wings.check_update'))
                        ->authorize(fn (Plugin $record) => user()?->can('view', $record))
                        ->icon(TablerIcon::Refresh)
                        ->color('gray')
                        ->visible(fn (Plugin $record) => $record instanceof WingsPlugin && $record->canCheckForUpdate())
                        ->action(function (WingsPlugin $record) {
                            $result = WingsPlugin::checkForUpdates([$record])[$record->id] ?? null;

                            self::notifyUpdateCheck($record, $result);
                        }),
                    Action::make('exclude_enable')
                        ->label(trans('admin/plugin.enable'))
                        ->authorize(fn (Plugin $record) => user()?->can('update', $record))
                        ->icon(TablerIcon::Check)
                        ->color('success')
                        ->visible(fn (Plugin $record) => $record->canEnable())
                        ->requiresConfirmation(fn (Plugin $record, PluginService $pluginService) => $record->isTheme() && !$record->isSwitchableTheme() && $pluginService->hasAnyThemeEnabled())
                        ->modalHidden(fn (Plugin $record, PluginService $pluginService) => $record->isSwitchableTheme() ? !user()?->can('update settings') : !($record->isTheme() && $pluginService->hasAnyThemeEnabled()))
                        ->modalHeading(fn (Plugin $record) => $record->isSwitchableTheme() ? trans('admin/plugin.default_theme_modal.heading') : trans('admin/plugin.enable_theme_modal.heading'))
                        ->modalDescription(fn (Plugin $record) => $record->isSwitchableTheme() ? trans('admin/plugin.default_theme_modal.description') : trans('admin/plugin.enable_theme_modal.description'))
                        ->schema(fn (Plugin $record) => $record->isSwitchableTheme() && user()?->can('update settings') ? static::defaultThemeSchema() : null)
                        ->action(function (Plugin $record, array $data, $livewire, PluginService $pluginService, ThemeService $themeService) {
                            if ($record instanceof WingsPlugin) {
                                self::runOnNode(fn () => $record->repository()->enable($record->plugin_id), 'enable_error', 'enabled');

                                return;
                            }

                            $pluginService->enablePlugin($record);

                            if (($data['set_as_default'] ?? false) && user()?->can('update settings')) {
                                $themeService->setDefaultTheme($record->id, (bool) ($data['force_theme'] ?? false));
                            }

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.enabled'))
                                ->send();
                        }),
                    Action::make('exclude_set_default_theme')
                        ->label(trans('admin/plugin.set_default_theme'))
                        ->authorize(fn (Plugin $record) => user()?->can('update', $record) && user()->can('update settings'))
                        ->icon(TablerIcon::Palette)
                        ->visible(fn (Plugin $record, ThemeService $themeService) => $record->isSwitchableTheme() && $record->status === PluginStatus::Enabled && $themeService->getDefaultThemeId() !== $record->id)
                        ->modalHeading(trans('admin/plugin.default_theme_modal.heading'))
                        ->schema([
                            Toggle::make('force_theme')
                                ->label(trans('admin/plugin.default_theme_modal.force_theme'))
                                ->helperText(trans('admin/plugin.default_theme_modal.force_theme_help'))
                                ->default(fn (ThemeService $themeService) => $themeService->isForced()),
                        ])
                        ->action(function (Plugin $record, array $data, $livewire, ThemeService $themeService) {
                            $themeService->setDefaultTheme($record->id, (bool) ($data['force_theme'] ?? false));

                            redirect(ListPlugins::getUrl(['tab' => $livewire->activeTab]));

                            Notification::make()
                                ->success()
                                ->title(trans('admin/plugin.notifications.default_theme_set'))
                                ->body($record->name)
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
                                self::runOnNode(fn () => $record->repository()->disable($record->plugin_id), 'disable_error', 'disabled');

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
                        ->modalDescription(fn (Plugin $record) => $record instanceof WingsPlugin ? trans('admin/plugin.wings.delete_description') : null)
                        ->visible(fn (Plugin $record) => $record instanceof WingsPlugin ? $record->canUninstall() : $record->status === PluginStatus::NotInstalled || $record->status === PluginStatus::Errored)
                        ->action(function (Plugin $record, $livewire, PluginService $pluginService) {
                            if ($record instanceof WingsPlugin) {
                                self::runOnNode(fn () => $record->repository()->uninstall($record->plugin_id, true), 'uninstall_error', 'deleted');

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
                        ->modalDescription(fn (Plugin $record) => $record instanceof WingsPlugin ? trans('admin/plugin.wings.uninstall_description') : null)
                        ->visible(fn (Plugin $record) => $record instanceof WingsPlugin ? $record->canUninstall() : !($record->status === PluginStatus::NotInstalled || $record->status === PluginStatus::Errored))
                        ->action(function (Plugin $record) {
                            if ($record instanceof WingsPlugin) {
                                self::runOnNode(fn () => $record->repository()->uninstall($record->plugin_id), 'uninstall_error', 'uninstalled');

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
                // From the node's own registries, signed by a key the node trusts (contract L "signed")
                Action::make('wings_install')
                    ->label(trans('admin/plugin.wings.install'))
                    ->modalHeading(trans('admin/plugin.wings.install'))
                    ->modalDescription(trans('admin/plugin.wings.install_description'))
                    ->hiddenLabel()
                    ->tooltip(trans('admin/plugin.wings.install'))
                    ->icon(TablerIcon::Download)
                    ->authorize(fn () => user()?->can('create', WingsPlugin::class))
                    ->visible(fn ($livewire) => $livewire->activeTab === 'wings' && WingsPlugin::nodesAllowing('signed')->isNotEmpty())
                    ->schema([
                        Select::make('node')
                            ->label(trans('admin/plugin.node'))
                            ->options(fn () => WingsPlugin::nodesAllowing('signed')->all())
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('plugin', null))
                            ->required(),
                        // Picked from the node's own registries (A2 "available"), as "@namespace/name" (R3)
                        Select::make('plugin')
                            ->label(trans('admin/plugin.wings.plugin_name'))
                            ->helperText(trans('admin/plugin.wings.plugin_pick_help'))
                            ->options(fn (Get $get) => self::availableOptions($get('node')))
                            ->disableOptionWhen(fn (string $value, Get $get) => self::availableInstalled($get('node'), $value))
                            ->searchable()
                            ->visible(fn (Get $get) => filled($get('node')))
                            ->required(),
                        TextEntry::make('registry_errors')
                            ->label(trans('admin/plugin.wings.registry_errors'))
                            ->state(fn (Get $get) => self::availableProblems($get('node')))
                            ->color('warning')
                            ->visible(fn (Get $get) => self::availableProblems($get('node')) !== null),
                    ])
                    ->modalSubmitActionLabel(trans('admin/plugin.wings.stage'))
                    ->action(function (array $data, ListPlugins $livewire, WingsPluginApprovals $approvals) {
                        $node = self::nodeAllowing($data['node'] ?? null, 'signed');
                        if (!$node) {
                            self::notifyNodeNotAllowed('install_error');

                            return;
                        }

                        $picked = self::parseAvailableKey($data['plugin'] ?? null);
                        if ($picked === null) {
                            Notification::make()->danger()->title(trans('admin/plugin.notifications.install_error'))->body(trans('admin/plugin.wings.invalid_id'))->send();

                            return;
                        }

                        [$registry, $plugin] = $picked;

                        self::stageOnNode($livewire, $approvals, $node, 'install', $plugin, ['registry' => $registry]);
                    }),
                // From any https URL or an uploaded archive (contract L "full")
                Action::make('wings_import')
                    ->label(trans('admin/plugin.wings.import'))
                    ->modalHeading(trans('admin/plugin.wings.import'))
                    ->modalDescription(trans('admin/plugin.wings.import_description'))
                    ->hiddenLabel()
                    ->tooltip(trans('admin/plugin.wings.import'))
                    ->icon(TablerIcon::FileUpload)
                    ->authorize(fn () => user()?->can('create', WingsPlugin::class))
                    ->visible(fn ($livewire) => $livewire->activeTab === 'wings' && WingsPlugin::nodesAllowing('full')->isNotEmpty())
                    ->schema([
                        Select::make('node')
                            ->label(trans('admin/plugin.node'))
                            ->options(fn () => WingsPlugin::nodesAllowing('full')->all())
                            ->required(),
                        ToggleButtons::make('source')
                            ->label(trans('admin/plugin.wings.source'))
                            ->options(['url' => trans('admin/plugin.wings.source_url'), 'file' => trans('admin/plugin.wings.source_file')])
                            ->default('url')
                            ->inline()
                            ->live()
                            ->required(),
                        TextInput::make('url')
                            ->label(trans('admin/plugin.wings.url'))
                            ->url()
                            ->startsWith(['https://'])
                            ->maxLength(2048)
                            ->visible(fn (Get $get) => $get('source') !== 'file')
                            ->required(fn (Get $get) => $get('source') !== 'file'),
                        TextInput::make('sha256')
                            ->label(trans('admin/plugin.wings.sha256'))
                            ->helperText(trans('admin/plugin.wings.sha256_help'))
                            ->regex('/^[0-9a-fA-F]{64}$/')
                            ->visible(fn (Get $get) => $get('source') !== 'file'),
                        FileUpload::make('file')
                            ->label(trans('admin/plugin.file'))
                            ->helperText(trans('admin/plugin.wings.file_help'))
                            ->storeFiles(false)
                            ->previewable(false)
                            ->preserveFilenames()
                            // Wings takes archives up to 200 MiB (A2)
                            ->maxSize(200 * 1024)
                            ->visible(fn (Get $get) => $get('source') === 'file')
                            ->required(fn (Get $get) => $get('source') === 'file'),
                        TextInput::make('signature')
                            ->label(trans('admin/plugin.wings.signature'))
                            ->helperText(trans('admin/plugin.wings.signature_help'))
                            ->maxLength(1024)
                            ->regex('/^[A-Za-z0-9+\/=]+$/'),
                    ])
                    ->modalSubmitActionLabel(trans('admin/plugin.wings.stage'))
                    ->action(function (array $data, ListPlugins $livewire, WingsPluginApprovals $approvals) {
                        $node = self::nodeAllowing($data['node'] ?? null, 'full');
                        if (!$node) {
                            self::notifyNodeNotAllowed('import_failed');

                            return;
                        }

                        $signature = filled($data['signature'] ?? null) ? (string) $data['signature'] : null;

                        if (($data['source'] ?? 'url') === 'file') {
                            $file = $data['file'] ?? null;
                            if (!$file instanceof UploadedFile) {
                                Notification::make()->danger()->title(trans('admin/plugin.notifications.import_failed'))->body(trans('admin/plugin.wings.file_missing'))->send();

                                return;
                            }

                            // An upload can't be staged again without the archive
                            self::stage($livewire, $approvals, $node, 'import', null, null, fn (DaemonPluginRepository $repository) => $repository->importFromFile($file, $signature));

                            return;
                        }

                        $url = (string) $data['url'];
                        $sha256 = filled($data['sha256'] ?? null) ? strtolower((string) $data['sha256']) : null;

                        self::stageOnNode($livewire, $approvals, $node, 'import', null, ['url' => $url, 'sha256' => $sha256, 'signature' => $signature]);
                    }),
                // Every plugin with an update URL on the admin's nodes, at once (A2 "update-check", level view)
                Action::make('wings_check_updates')
                    ->label(trans('admin/plugin.wings.check_updates'))
                    ->hiddenLabel()
                    ->tooltip(trans('admin/plugin.wings.check_updates'))
                    ->icon(TablerIcon::Refresh)
                    ->authorize(fn () => user()?->can('viewList plugin'))
                    ->visible(fn ($livewire) => $livewire->activeTab === 'wings')
                    ->action(function () {
                        $results = WingsPlugin::checkForUpdates(WingsPlugin::scoped()->get()->filter(fn (WingsPlugin $plugin) => $plugin->canCheckForUpdate()));

                        $found = collect($results)->filter(fn ($result) => is_array($result) && $result['available'])->count();
                        $failed = collect($results)->filter(fn ($result) => !is_array($result) || $result['error'] !== null)->count();

                        Notification::make()
                            ->status($failed > 0 ? 'warning' : 'success')
                            ->title(trans_choice('admin/plugin.wings.updates_found', $found, ['count' => $found]))
                            ->body($failed > 0 ? trans_choice('admin/plugin.wings.updates_failed', $failed, ['count' => $failed]) : null)
                            ->send();
                    }),
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
                    ->visible(fn ($livewire) => $livewire->activeTab !== 'wings')
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
                    ->visible(fn ($livewire) => $livewire->activeTab !== 'wings')
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
