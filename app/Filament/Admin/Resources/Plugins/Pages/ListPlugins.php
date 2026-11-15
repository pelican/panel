<?php

namespace App\Filament\Admin\Resources\Plugins\Pages;

use App\Enums\PluginCategory;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Plugins\PluginResource;
use App\Livewire\AlertBanner;
use App\Models\Node;
use App\Models\Plugin;
use App\Models\WingsPlugin;
use App\Repositories\Daemon\DaemonPluginRepository;
use App\Services\Helpers\PluginService;
use App\Services\Nodes\WingsPluginApprovals;
use App\Services\Nodes\WingsPluginFailure;
use Exception;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\RequestException;
use Livewire\Attributes\On;
use Throwable;

class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;

    private function isWingsTab(): bool
    {
        return $this->activeTab === 'wings';
    }

    /**
     * Only the nodes this admin can access are asked, and only their rows are listed (contract A7).
     *
     * @return Builder<WingsPlugin>
     */
    private function wingsPlugins(): Builder
    {
        return WingsPlugin::scoped();
    }

    // The Wings tab lists the plugins on the nodes
    protected function getTableQuery(): Builder|Relation|null
    {
        if ($this->isWingsTab()) {
            return $this->wingsPlugins();
        }

        return parent::getTableQuery();
    }

    /**
     * Only the Panel's own plugins have a load order; Wings answers 501 to one (contract A6).
     *
     * @param  array<int, string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        abort_unless($this->getTable()->isReorderable() && !$this->isWingsTab(), 403);

        /** @var PluginService $pluginService */
        $pluginService = app(PluginService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $pluginService->updateLoadOrder($order);
    }

    /**
     * Second step of a two-step install, update or import (contract v3, A4): shows the admin what the node
     * staged, and only on approval sends the token back to the endpoint that staged it.
     */
    public function approveWingsPluginAction(): Action
    {
        return Action::make('approveWingsPlugin')
            ->modalHeading(function (array $arguments, WingsPluginApprovals $approvals) {
                $pending = user() ? $approvals->get(user(), (string) ($arguments['approval'] ?? '')) : null;

                return $pending === null
                    ? trans('admin/plugin.wings.approval_missing')
                    : trans("admin/plugin.wings.approve_heading_{$pending['action']}", ['name' => $pending['plugin']['name'], 'node' => $pending['node_name']]);
            })
            ->modalDescription(trans('admin/plugin.wings.approve_description'))
            ->modalIcon(TablerIcon::AlertTriangle)
            ->modalIconColor('warning')
            ->schema(fn (array $arguments, WingsPluginApprovals $approvals) => self::approvalSchema(user() ? $approvals->get(user(), (string) ($arguments['approval'] ?? '')) : null))
            ->modalSubmitActionLabel(trans('admin/plugin.wings.approve'))
            ->color('warning')
            ->action(function (array $arguments, WingsPluginApprovals $approvals) {
                $user = user();
                $pending = $user ? $approvals->pull($user, (string) ($arguments['approval'] ?? '')) : null;

                if ($pending === null) {
                    Notification::make()->danger()->title(trans('admin/plugin.wings.approval_missing'))->send();

                    return;
                }

                $action = (string) $pending['action'];
                $errorKey = $action === 'import' ? 'import_failed' : "{$action}_error";

                // The admin's access may have changed since the plugin was staged (contract A7)
                $node = Node::find($pending['node_id']);
                $allowed = $action === 'update' ? $user->can('update plugin') : $user->can('create', WingsPlugin::class);
                if (!$node || !$user->canTarget($node) || !$allowed) {
                    Notification::make()->danger()->title(trans("admin/plugin.notifications.$errorKey"))->body(trans('admin/plugin.wings.node_not_allowed'))->send();

                    return;
                }

                try {
                    (new DaemonPluginRepository())->setNode($node)->approve($action, $pending['plugin_id'], (string) $pending['token']);
                } catch (Exception $exception) {
                    if (!$exception instanceof RequestException) {
                        report($exception);
                    }

                    // Wings re-checks the stage against the node as it is now (A4): 403, 422 or 409
                    $refusal = WingsPluginFailure::approvalRefusal($exception);
                    $restage = $refusal !== null ? $approvals->putRestage($user, $pending) : null;

                    $notification = Notification::make()
                        ->danger()
                        ->title(trans("admin/plugin.notifications.$errorKey"))
                        ->body($refusal === null
                            ? WingsPluginFailure::message($exception)
                            : $refusal . ' ' . trans($restage === null ? 'admin/plugin.wings.approval_upload_again' : 'admin/plugin.wings.approval_stage_again'));

                    if ($restage !== null) {
                        $notification
                            ->persistent()
                            ->actions([
                                Action::make('restage')
                                    ->label(trans('admin/plugin.wings.stage_again'))
                                    ->button()
                                    ->dispatch('restage-wings-plugin', ['handle' => $restage]),
                            ]);
                    }

                    $notification->send();

                    return;
                } finally {
                    WingsPlugin::flush();
                }

                Notification::make()
                    ->success()
                    ->title(trans($action === 'update' ? 'admin/plugin.notifications.updated' : 'admin/plugin.notifications.installed'))
                    ->body(trans('admin/plugin.wings.installed_on', ['name' => $pending['plugin']['name'] ?? '?', 'node' => $node->name]))
                    ->send();
            });
    }

    /**
     * Stages again what the node refused to approve, after the same checks as the first time (A4, A7):
     * the admin's access to the node, their permission, and the node's current level.
     */
    #[On('restage-wings-plugin')]
    public function restageWingsPlugin(string $handle, WingsPluginApprovals $approvals): void
    {
        $user = user();
        $restage = $user ? $approvals->pullRestage($user, $handle) : null;

        if ($restage === null) {
            Notification::make()->danger()->title(trans('admin/plugin.wings.approval_missing'))->send();

            return;
        }

        $action = $restage['action'];
        $errorKey = $action === 'import' ? 'import_failed' : "{$action}_error";
        $allowed = $action === 'update' ? $user->can('update plugin') : $user->can('create', WingsPlugin::class);
        $node = $allowed ? PluginResource::nodeAllowing($restage['node_id'], $action === 'import' ? 'full' : 'signed') : null;

        if (!$node) {
            Notification::make()->danger()->title(trans("admin/plugin.notifications.$errorKey"))->body(trans('admin/plugin.wings.node_not_allowed'))->send();

            return;
        }

        PluginResource::stageOnNode($this, $approvals, $node, $action, $restage['plugin_id'], $restage['source']);
    }

    /**
     * @param  array<string, mixed>|null  $pending
     * @return Component[]
     */
    private static function approvalSchema(?array $pending): array
    {
        if ($pending === null) {
            return [
                TextEntry::make('missing')->hiddenLabel()->state(trans('admin/plugin.wings.approval_missing')),
            ];
        }

        $plugin = $pending['plugin'];
        $list = fn (array $items) => $items === [] ? trans('admin/plugin.wings.none') : implode(', ', $items);

        return [
            TextEntry::make('node')->label(trans('admin/plugin.node'))->state($pending['node_name']),
            TextEntry::make('plugin')->label(trans('admin/plugin.name'))->state(trim($plugin['name'] . ' ' . ($plugin['version'] ?? ''))),
            TextEntry::make('author')->label(trans('admin/plugin.author'))->state($plugin['author'] ?? '-'),
            TextEntry::make('description')->label(trans('admin/plugin.wings.description'))->state($plugin['description'] ?? '-'),
            TextEntry::make('source')->label(trans('admin/plugin.wings.source'))->state($plugin['source'] ?? '-'),
            TextEntry::make('capability')->label(trans('admin/plugin.wings.capability'))->state($plugin['capability'] ?? '-'),
            TextEntry::make('entrypoint')->label(trans('admin/plugin.wings.entrypoint'))->state($plugin['entrypoint'] === [] ? '-' : implode(' ', $plugin['entrypoint'])),
            TextEntry::make('events')->label(trans('admin/plugin.wings.events'))->state($list($plugin['events'])),
            TextEntry::make('host_access')
                ->label(trans('admin/plugin.wings.host_access'))
                ->helperText(trans('admin/plugin.wings.host_access_help'))
                ->state($list($pending['host_access']))
                ->color($pending['host_access'] === [] ? null : 'warning'),
            TextEntry::make('replaces')
                ->label(trans('admin/plugin.wings.replaces'))
                ->state($pending['replaces'] ?? trans('admin/plugin.wings.replaces_none')),
            // True only when the signature verified against a key the node trusts (A4)
            TextEntry::make('signed')
                ->label(trans('admin/plugin.wings.signed'))
                ->state($pending['signed'] ? trans('admin/plugin.wings.signed_yes') : trans('admin/plugin.wings.signed_no'))
                ->color($pending['signed'] ? 'success' : 'danger'),
            TextEntry::make('expires_at')->label(trans('admin/plugin.wings.expires_at'))->state($pending['expires_at'])->dateTime(),
        ];
    }

    public function mount(): void
    {
        parent::mount();

        if (config('panel.plugin.safe_mode')) {
            AlertBanner::make('plugin_safe_mode')
                ->title(trans('admin/plugin.safe_mode_active'))
                ->warning()
                ->send();
        }
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('all')
                ->label(trans('admin/plugin.all'))
                ->badge(Plugin::count()),
        ];

        foreach (PluginCategory::cases() as $category) {
            $query = Plugin::whereCategory($category->value);
            $tabs[$category->value] = Tab::make($category->value)
                ->label($category->getLabel())
                ->icon($category->getIcon())
                ->badge($query->count())
                ->modifyQueryUsing(fn () => $query);
        }

        $tabs['wings'] = Tab::make('wings')
            ->label(trans('admin/plugin.tabs_wings'))
            ->icon(TablerIcon::Server2)
            ->badge(fn () => $this->wingsBadge());

        return $tabs;
    }

    /**
     * The number of plugins on the admin's nodes. Nodes are only asked on the Wings tab, and a node that
     * didn't answer or a plugin that couldn't be read makes the count unknown. A node that doesn't let the
     * Panel list its plugins is known to have none to show.
     */
    private function wingsBadge(): ?string
    {
        if (!$this->isWingsTab() && !WingsPlugin::isCached()) {
            return null;
        }

        try {
            $rows = $this->wingsPlugins()->get();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if ($rows->contains(fn (WingsPlugin $row) => $row->row_type === WingsPlugin::ROW_ERROR)) {
            return null;
        }

        $count = $rows->filter(fn (WingsPlugin $row) => $row->isPlugin())->count();

        return $count > 0 ? (string) $count : null;
    }
}
