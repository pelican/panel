<?php

namespace App\Models;

use App\Enums\PluginStatus;
use App\Repositories\Daemon\DaemonPluginRepository;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Connection;
use Illuminate\Support\Arr;
use Throwable;

/**
 * A plugin installed on a node's Wings daemon.
 *
 * Wings plugins are a separate population from the Panel's own. They are Go
 * source living on a node, they hook things only the daemon can see, such as
 * SFTP deletes and container creation, and they are enabled per node rather
 * than per installation.
 *
 * This extends {@see Plugin} so that the existing plugin table can render both
 * without a second set of columns: every column the table reads exists here
 * too, with the same meaning. What differs is where the rows come from, which
 * is an HTTP call to each node rather than the plugins directory on disk, and
 * what the actions do, which is talk to the daemon rather than the Panel's own
 * plugin service.
 *
 * @property int $node_id
 * @property string $node_name
 * @property string $plugin_id
 * @property string|null $wings_version
 * @property string|null $package
 * @property string $hooks
 * @property string $settings
 * @property string $values
 * @property int $is_trusted
 * @property int $can_enable
 * @property int $can_disable
 * @property int $can_uninstall
 * @property int $subsystem_enabled
 * @property string|null $error
 */
class WingsPlugin extends Plugin
{
    public const RESOURCE_NAME = 'wings_plugin';

    /**
     * Sushi keeps its SQLite connection in a static property, and a static
     * declared on Plugin is shared with everything extending it. Sharing one
     * connection would mean whichever model booted first created its table and
     * the other's query hit a database that has never heard of it.
     *
     * Redeclaring the property here gives this model its own storage for it,
     * so each has its own in-memory database and its own table.
     *
     * @var Connection|null
     */
    protected static $sushiConnection;

    /**
     * How long a node's plugin list is reused for.
     *
     * Listing plugins means one request per node, and the page re-reads rows
     * several times while rendering. A short window keeps a page load to one
     * request per node while still reflecting an action within seconds of it
     * being taken; every action clears this anyway.
     */
    private const CacheSeconds = 10;

    /** @return string[] */
    public function getSchema(): array
    {
        return [
            'id' => 'string',
            'node_id' => 'integer',
            'node_name' => 'string',
            'plugin_id' => 'string',
            'name' => 'string',
            'author' => 'string',
            'version' => 'string',
            'description' => 'string',
            'category' => 'string',
            'url' => 'string',
            'update_url' => 'string',
            'wings_version' => 'string',
            'api_version' => 'integer',
            'package' => 'string',
            'status' => 'string',
            'status_message' => 'string',
            'load_order' => 'integer',
            'hooks' => 'string',
            'settings' => 'string',
            'values' => 'string',
            'is_trusted' => 'integer',
            'can_enable' => 'integer',
            'can_disable' => 'integer',
            'can_uninstall' => 'integer',
            'subsystem_enabled' => 'integer',
            'error' => 'string',
        ];
    }

    /**
     * Read every reachable node's plugin list.
     *
     * A node that cannot be reached contributes one row describing the failure
     * rather than being skipped. Skipping it would make a broken node look like
     * a node with no plugins, which is exactly the wrong impression when
     * something has gone wrong.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return cache()->remember('wings_plugins.rows', now()->addSeconds(self::CacheSeconds), function (): array {
            $rows = [];

            foreach (Node::all() as $node) {
                try {
                    $response = (new DaemonPluginRepository())->setNode($node)->getPlugins();
                } catch (Throwable $exception) {
                    report($exception);

                    $rows[] = self::errorRow($node, $exception->getMessage());

                    continue;
                }

                // Either the node's Wings predates plugins, or an operator has
                // switched the subsystem off. Neither is something to show a
                // row about; the node simply has no plugins to manage.
                if (!$response['enabled']) {
                    continue;
                }

                foreach ($response['data'] as $plugin) {
                    $rows[] = self::row($node, $plugin);
                }
            }

            return $rows;
        });
    }

    /**
     * Build one row from a node's response.
     *
     * @param  array<string, mixed>  $plugin
     * @return array<string, mixed>
     */
    private static function row(Node $node, array $plugin): array
    {
        $meta = $plugin['meta'] ?? [];

        return [
            // The id has to be unique across every node, since one page lists
            // them all and the same plugin is commonly installed on several.
            'id' => $node->id . ':' . $plugin['id'],
            'node_id' => $node->id,
            'node_name' => $node->name,
            'plugin_id' => $plugin['id'],

            'name' => $plugin['name'] ?? $plugin['id'],
            'author' => $plugin['author'] ?? 'Unknown',
            'version' => $plugin['version'] ?? '0.0.0',
            'description' => $plugin['description'] ?? null,
            'category' => $plugin['category'] ?? 'plugin',
            'url' => $plugin['url'] ?: null,
            'update_url' => $plugin['update_url'] ?: null,

            'wings_version' => $plugin['wings_version'] ?: null,
            'api_version' => $plugin['api_version'] ?? 1,
            'package' => $plugin['package'] ?? null,

            'status' => $meta['status'] ?? PluginStatus::NotInstalled->value,
            'status_message' => $meta['status_message'] ?: null,
            'load_order' => Arr::integer($meta, 'load_order', 0),

            'hooks' => implode(',', Arr::get($meta, 'registered.hooks', []) ?? []),

            // Kept as JSON so the settings form can be built from what the
            // plugin's manifest declares without another round trip.
            'settings' => json_encode($plugin['settings'] ?? [], JSON_THROW_ON_ERROR),
            'values' => json_encode($meta['values'] ?? [], JSON_THROW_ON_ERROR),

            'is_trusted' => (int) ($meta['is_trusted'] ?? false),
            'can_enable' => (int) ($meta['can_enable'] ?? false),
            'can_disable' => (int) ($meta['can_disable'] ?? false),
            'can_uninstall' => (int) ($meta['can_uninstall'] ?? false),

            'subsystem_enabled' => 1,
            'error' => null,
        ];
    }

    /** @return array<string, mixed> */
    private static function errorRow(Node $node, string $message): array
    {
        return [
            'id' => $node->id . ':unreachable',
            'node_id' => $node->id,
            'node_name' => $node->name,
            'plugin_id' => '',

            'name' => $node->name,
            'author' => '',
            'version' => '',
            'description' => $message,
            'category' => 'plugin',
            'url' => null,
            'update_url' => null,

            'wings_version' => null,
            'api_version' => 0,
            'package' => null,

            'status' => PluginStatus::Errored->value,
            'status_message' => $message,
            'load_order' => 0,
            'hooks' => '',
            'settings' => '[]',
            'values' => '{}',

            'is_trusted' => 0,
            'can_enable' => 0,
            'can_disable' => 0,
            'can_uninstall' => 0,

            'subsystem_enabled' => 0,
            'error' => $message,
        ];
    }

    /**
     * Only the status is cast. Category stays a string because Wings has
     * categories the Panel's enum does not, such as backup and parser, and
     * forcing them through it would either lose them or throw.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PluginStatus::class,
        ];
    }

    public function node(): ?Node
    {
        return Node::find($this->node_id);
    }

    public function repository(): DaemonPluginRepository
    {
        return (new DaemonPluginRepository())->setNode($this->node());
    }

    /** @return string[] */
    public function hookList(): array
    {
        return array_values(array_filter(explode(',', (string) $this->hooks)));
    }

    /** @return array<int, array<string, mixed>> */
    public function declaredSettings(): array
    {
        return json_decode((string) $this->settings, true, 512, JSON_THROW_ON_ERROR) ?: [];
    }

    /** @return array<string, mixed> */
    public function settingValues(): array
    {
        return json_decode((string) $this->values, true, 512, JSON_THROW_ON_ERROR) ?: [];
    }

    public function isReachable(): bool
    {
        return (bool) $this->subsystem_enabled;
    }

    /**
     * Forget the cached rows so the next read reflects an action that was just
     * taken, rather than the state from before it.
     */
    public static function flush(): void
    {
        cache()->forget('wings_plugins.rows');

        self::refreshRows();
    }

    // The rest of this class turns off the parts of Plugin that only make sense
    // for a plugin living in the Panel's own plugins directory. A Wings plugin
    // is not on this filesystem and has no PHP class, so anything that would go
    // looking for one answers for the remote plugin instead.

    public function canEnable(): bool
    {
        return (bool) $this->can_enable;
    }

    public function canDisable(): bool
    {
        return (bool) $this->can_disable;
    }

    public function canUninstall(): bool
    {
        return (bool) $this->can_uninstall;
    }

    public function isCompatible(): bool
    {
        // Wings decides this, since it is the thing whose version matters, and
        // it has already reported the answer as the plugin's status.
        return $this->status !== PluginStatus::Incompatible;
    }

    public function isUpdateAvailable(): bool
    {
        // Checking would mean a request per plugin to its update url, from the
        // Panel, for a plugin on another machine. The node checks this itself
        // when an update is actually requested.
        return false;
    }

    public function hasSettings(): bool
    {
        return $this->declaredSettings() !== [];
    }

    /** @return array<string, mixed> */
    public function getSettingsFormData(): array
    {
        return $this->settingValues();
    }

    /**
     * Build the settings form from what the plugin's manifest declares.
     *
     * A Panel plugin hands over Filament components directly, because it is
     * loaded into this process. A Wings plugin cannot: it runs on another
     * machine and may not be running at all. So it declares its settings in
     * plugin.json instead, and this turns that declaration into a form, which
     * is what lets an admin configure a plugin that is disabled or has never
     * been enabled.
     *
     * @return Component[]
     */
    public function getSettingsForm(): array
    {
        $fields = [];

        foreach ($this->declaredSettings() as $setting) {
            $key = $setting['key'] ?? null;
            if (!$key) {
                continue;
            }

            $field = match ($setting['type'] ?? 'string') {
                'boolean' => Toggle::make($key)->inline(false),
                'number' => TextInput::make($key)->numeric(),
                'text' => Textarea::make($key)->rows(4),
                'secret' => TextInput::make($key)->password()->revealable(),
                'select' => Select::make($key)
                    ->options(collect($setting['options'] ?? [])
                        ->mapWithKeys(fn (array $option): array => [$option['value'] => $option['label']])
                        ->all())
                    ->selectablePlaceholder(false),
                default => TextInput::make($key),
            };

            $fields[] = $field
                ->label($setting['label'] ?: $key)
                ->helperText($setting['description'] ?? null)
                ->required((bool) ($setting['required'] ?? false));
        }

        return $fields;
    }

    /**
     * Save settings on the node.
     *
     * Wings rejects any key its manifest does not declare, so a form that has
     * drifted from the plugin fails loudly instead of writing values nothing
     * will ever read.
     *
     * @param  array<mixed, mixed>  $data
     */
    public function saveSettings(array $data): void
    {
        $this->repository()->saveSettings($this->plugin_id, $data);

        self::flush();
    }

    public function getReadme(): ?string
    {
        return null;
    }

    public function isTheme(): bool
    {
        return false;
    }

    public function isLanguage(): bool
    {
        return false;
    }

    public function shouldLoad(?string $panelId = null): bool
    {
        return false;
    }

    /** @return string[] */
    public function getProviders(): array
    {
        return [];
    }

    /** @return string[] */
    public function getCommands(): array
    {
        return [];
    }

    public function getSeeder(): ?string
    {
        return null;
    }
}
