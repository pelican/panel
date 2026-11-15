<?php

namespace App\Models;

use App\Enums\PluginStatus;
use App\Repositories\Daemon\DaemonPluginRepository;
use App\Services\Nodes\WingsPluginFailure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * A plugin installed on a node's Wings daemon, listed next to the Panel's own plugins.
 *
 * Rows follow the Wings plugin API (pelican/wings contract v3, A3). A node that can't list its plugins
 * gets one row of its own: "unavailable" for its access level or a Wings without the plugin API,
 * "error" when it didn't answer. Only rows of type "plugin" are real plugins.
 *
 * Rows only ever hold the nodes of one scope (the current admin's accessible nodes): use scoped().
 *
 * @property int $node_id
 * @property string $node_name
 * @property string $plugin_id
 * @property string $row_type
 * @property string|null $panel_access
 * @property string|null $kind
 * @property string|null $wings_version
 * @property string|null $trust
 * @property string $hooks
 * @property string $host_access
 * @property string $settings
 * @property string $values
 * @property int $is_trusted
 * @property int $can_enable
 * @property int $can_disable
 * @property int $can_configure
 * @property int $can_update
 * @property int $can_uninstall
 * @property string|null $error
 */
class WingsPlugin extends Plugin
{
    public const RESOURCE_NAME = 'wings_plugin';

    public const ROW_PLUGIN = 'plugin';

    public const ROW_UNAVAILABLE = 'unavailable';

    public const ROW_ERROR = 'error';

    /** Statuses Wings sends (A3); anything else is shown as errored. */
    private const STATUSES = ['enabled', 'disabled', 'errored'];

    private const TRUST = ['native', 'trusted', 'untrusted', 'changed'];

    /** Setting types of contract v3 (A3.1). */
    public const SETTING_TYPES = ['string', 'number', 'boolean', 'select'];

    /**
     * Setting keys Wings accepts (A3.1). Anything else, such as a dotted key that the form would read as
     * nesting, is shown read-only and its stored value is sent back as it was.
     */
    public const SETTING_KEY_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9_-]*$/';

    /** What Wings shows instead of a stored secret, and keeps it when sent back. */
    public const MASKED_SECRET = '********';

    private const CACHE_PREFIX = 'wings_plugins.';

    private const CACHE_GENERATION = 'wings_plugins.generation';

    private const CACHE_SECONDS = 10;

    /** How long the answer of an update check is kept; it is keyed on the installed version too. */
    private const UPDATE_CHECK_SECONDS = 3600;

    /**
     * Needs its own connection, Plugin's static one is shared with subclasses.
     *
     * @var Connection|null
     */
    protected static $sushiConnection;

    /**
     * Node ids whose plugins the Sushi table holds; null until scoped() is called, and then no node is asked.
     *
     * @var int[]|null
     */
    private static ?array $scopeNodeIds = null;

    /** @return string[] */
    public function getSchema(): array
    {
        return [
            'id' => 'string',
            'node_id' => 'integer',
            'node_name' => 'string',
            'plugin_id' => 'string',
            'row_type' => 'string',
            'panel_access' => 'string',
            'name' => 'string',
            'author' => 'string',
            'version' => 'string',
            'description' => 'string',
            'category' => 'string',
            'kind' => 'string',
            'url' => 'string',
            'update_url' => 'string',
            'wings_version' => 'string',
            'api_version' => 'integer',
            'status' => 'string',
            'status_message' => 'string',
            'trust' => 'string',
            'load_order' => 'integer',
            'hooks' => 'string',
            'host_access' => 'string',
            'settings' => 'string',
            'values' => 'string',
            'is_trusted' => 'integer',
            'can_enable' => 'integer',
            'can_disable' => 'integer',
            'can_configure' => 'integer',
            'can_update' => 'integer',
            'can_uninstall' => 'integer',
            'error' => 'string',
        ];
    }

    /**
     * The Wings plugins of the nodes $user may access (contract A7). Root admins see every node.
     *
     * @return Builder<static>
     */
    public static function scoped(?User $user = null): Builder
    {
        $nodeIds = self::accessibleNodeIds($user ?? user());

        if (self::$scopeNodeIds !== $nodeIds) {
            self::$scopeNodeIds = $nodeIds;

            static::refreshRows();
        }

        // Filtered again, in case the table was filled for another scope
        return static::query()->whereIn('node_id', $nodeIds);
    }

    /** @return int[] */
    private static function accessibleNodeIds(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return $user->accessibleNodes()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * What each accessible node said about its plugins: its access level, and whether it could list them.
     *
     * @return array<int, array{name: string, state: string, panel_access: ?string, required: ?string}>
     */
    public static function nodeStates(?User $user = null): array
    {
        $nodeIds = self::accessibleNodeIds($user ?? user());

        return $nodeIds === [] ? [] : self::inventory($nodeIds)['nodes'];
    }

    /** Whether the plugins of $user's nodes are cached, so reading them doesn't ask the nodes. */
    public static function isCached(?User $user = null): bool
    {
        $nodeIds = self::accessibleNodeIds($user ?? user());

        return $nodeIds === [] || cache()->has(self::cacheKey($nodeIds));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        $nodeIds = self::$scopeNodeIds ?? [];

        return $nodeIds === [] ? [] : self::inventory($nodeIds)['rows'];
    }

    /** @param  int[]  $nodeIds */
    private static function cacheKey(array $nodeIds): string
    {
        return self::CACHE_PREFIX . (int) cache()->get(self::CACHE_GENERATION, 0) . '.' . sha1(implode(',', $nodeIds));
    }

    /**
     * Asks every node in $nodeIds at once, cached per set of nodes.
     *
     * @param  int[]  $nodeIds
     * @return array{rows: array<int, array<string, mixed>>, nodes: array<int, array{name: string, state: string, panel_access: ?string, required: ?string}>}
     */
    private static function inventory(array $nodeIds): array
    {
        return cache()->remember(self::cacheKey($nodeIds), now()->addSeconds(self::CACHE_SECONDS), function () use ($nodeIds): array {
            $nodes = Node::query()->whereIn('id', $nodeIds)->orderBy('id')->get();

            $promises = [];
            foreach ($nodes as $node) {
                $promises[$node->id] = (new DaemonPluginRepository())->setNode($node)->getPluginsAsync();
            }

            $results = Utils::settle($promises)->wait();

            $rows = [];
            $states = [];
            foreach ($nodes as $node) {
                $result = $results[$node->id];

                if ($result['state'] !== PromiseInterface::FULFILLED) {
                    $reason = $result['reason'];

                    if ($reason instanceof Throwable) {
                        report($reason);
                    }

                    $message = $reason instanceof Throwable ? $reason->getMessage() : (string) $reason;
                    $rows[] = self::nodeRow($node, self::ROW_ERROR, trans('admin/plugin.wings.unreachable', ['message' => $message]), '.unreachable');
                    $states[$node->id] = ['name' => $node->name, 'state' => 'unreachable', 'panel_access' => null, 'required' => null];

                    continue;
                }

                $list = $result['value'];
                $states[$node->id] = [
                    'name' => $node->name,
                    'state' => $list['available'] ? 'available' : (string) $list['reason'],
                    'panel_access' => $list['panel_access'],
                    'required' => $list['required'],
                ];

                if (!$list['available']) {
                    $message = $list['reason'] === 'access'
                        ? trans('admin/plugin.wings.not_available_level', ['level' => $list['panel_access'], 'required' => $list['required']])
                        : trans('admin/plugin.wings.not_available_api');

                    $rows[] = self::nodeRow($node, self::ROW_UNAVAILABLE, $message, '.unavailable', $list['panel_access']);

                    continue;
                }

                // Discovery found a bad manifest; the plugins that loaded are still listed
                if ($list['error'] !== null) {
                    $rows[] = self::nodeRow($node, self::ROW_ERROR, $list['error'], '.discovery', $list['panel_access']);
                }

                foreach ($list['data'] as $index => $plugin) {
                    try {
                        $rows[] = self::row($node, $plugin, $list['panel_access']);
                    } catch (Throwable $exception) {
                        // One malformed plugin shouldn't hide the others, but it is still reported
                        report($exception);

                        $name = is_array($plugin) && is_string($plugin['name'] ?? null) ? $plugin['name'] : null;
                        $rows[] = self::nodeRow($node, self::ROW_ERROR, $exception->getMessage(), ".invalid-$index", $list['panel_access'], $name);
                    }
                }
            }

            return ['rows' => $rows, 'nodes' => $states];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(Node $node, mixed $plugin, ?string $panelAccess): array
    {
        if (!is_array($plugin)) {
            throw new InvalidArgumentException('The node listed a plugin that is not an object.');
        }

        $id = $plugin['id'] ?? null;
        if (!DaemonPluginRepository::isValidId($id)) {
            throw new InvalidArgumentException('The node listed a plugin with an empty or invalid id.');
        }

        $meta = is_array($plugin['meta'] ?? null) ? $plugin['meta'] : [];

        $status = is_string($meta['status'] ?? null) && in_array($meta['status'], self::STATUSES, true)
            ? PluginStatus::from($meta['status'])
            : PluginStatus::Errored;

        $hooks = $meta['registered']['hooks'] ?? [];
        if (!is_array($hooks)) {
            throw new InvalidArgumentException("The plugin \"$id\" listed its hooks as something other than a list.");
        }

        $settings = $plugin['settings'] ?? [];
        if (!is_array($settings)) {
            throw new InvalidArgumentException("The plugin \"$id\" listed its settings as something other than a list.");
        }

        $values = $meta['values'] ?? [];

        $trust = is_string($meta['trust'] ?? null) && in_array($meta['trust'], self::TRUST, true) ? $meta['trust'] : null;

        $flag = fn (string $key): int => ($meta[$key] ?? false) === true ? 1 : 0;

        return [
            // Unique across nodes, as the same plugin is often installed on several
            'id' => $node->id . ':' . $id,
            'node_id' => $node->id,
            'node_name' => $node->name,
            'plugin_id' => $id,
            'row_type' => self::ROW_PLUGIN,
            'panel_access' => $panelAccess,

            'name' => self::string($plugin, 'name') ?? $id,
            'author' => self::string($plugin, 'author') ?? '',
            'version' => self::string($plugin, 'version') ?? '',
            'description' => self::string($plugin, 'description'),
            'category' => self::string($plugin, 'category') ?? 'plugin',
            'kind' => self::string($plugin, 'kind'),
            // Omitted by Wings when empty
            'url' => self::string($plugin, 'url'),
            'update_url' => self::string($plugin, 'update_url'),
            'wings_version' => self::string($plugin, 'wings_version'),
            'api_version' => is_int($plugin['api_version'] ?? null) ? $plugin['api_version'] : 0,

            'status' => $status->value,
            'status_message' => self::string($meta, 'status_message') ?? self::string($meta, 'error'),
            'trust' => $trust,
            'load_order' => 0,

            'hooks' => implode(',', array_filter($hooks, 'is_string')),
            'host_access' => implode(',', array_filter(is_array($plugin['host_access'] ?? null) ? $plugin['host_access'] : [], 'is_string')),

            'settings' => json_encode(array_values(array_filter($settings, 'is_array')), JSON_THROW_ON_ERROR),
            'values' => json_encode(is_array($values) ? (object) $values : (object) [], JSON_THROW_ON_ERROR),

            'is_trusted' => $flag('is_trusted'),
            'can_enable' => $flag('can_enable'),
            'can_disable' => $flag('can_disable'),
            'can_configure' => $flag('can_configure'),
            'can_update' => $flag('can_update'),
            'can_uninstall' => $flag('can_uninstall'),

            'error' => null,
        ];
    }

    /** @param  array<mixed>  $from */
    private static function string(array $from, string $key): ?string
    {
        $value = $from[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A row for a node that can't list its plugins, or a plugin that couldn't be read. Its key starts with
     * "." after the node id, which no plugin id can (A1), so it can never be taken for a plugin.
     *
     * @return array<string, mixed>
     */
    private static function nodeRow(Node $node, string $type, string $message, string $key, ?string $panelAccess = null, ?string $name = null): array
    {
        return [
            'id' => $node->id . ':' . $key,
            'node_id' => $node->id,
            'node_name' => $node->name,
            'plugin_id' => '',
            'row_type' => $type,
            'panel_access' => $panelAccess,

            'name' => $name ?? $node->name,
            'author' => '',
            'version' => '',
            'description' => $message,
            'category' => 'plugin',
            'kind' => null,
            'url' => null,
            'update_url' => null,
            'wings_version' => null,
            'api_version' => 0,

            'status' => ($type === self::ROW_UNAVAILABLE ? PluginStatus::Unavailable : PluginStatus::Errored)->value,
            'status_message' => $message,
            'trust' => null,
            'load_order' => 0,
            'hooks' => '',
            'host_access' => '',
            'settings' => '[]',
            'values' => '{}',

            'is_trusted' => 0,
            'can_enable' => 0,
            'can_disable' => 0,
            'can_configure' => 0,
            'can_update' => 0,
            'can_uninstall' => 0,

            'error' => $type === self::ROW_ERROR ? $message : null,
        ];
    }

    /**
     * Category stays a string, Wings has categories the Panel's enum doesn't
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

    /**
     * The client for this plugin's node. Refuses anything that isn't a real plugin, or a node the
     * current admin can't access, so a crafted record can't reach another node (contract A7).
     */
    public function repository(): DaemonPluginRepository
    {
        if (!$this->isPlugin()) {
            throw new RuntimeException('This row is not a plugin.');
        }

        $node = $this->node() ?? throw new RuntimeException('The node of this plugin no longer exists');

        if (!user()?->canTarget($node)) {
            throw new RuntimeException('You cannot manage plugins on this node.');
        }

        return (new DaemonPluginRepository())->setNode($node);
    }

    /** Whether this row is a real plugin, rather than a node that couldn't list its plugins. */
    public function isPlugin(): bool
    {
        return $this->row_type === self::ROW_PLUGIN && DaemonPluginRepository::isValidId($this->plugin_id);
    }

    public function isUnavailable(): bool
    {
        return $this->row_type === self::ROW_UNAVAILABLE;
    }

    /** @return string[] */
    public function hookList(): array
    {
        return array_values(array_filter(explode(',', (string) $this->hooks)));
    }

    /** @return string[] */
    public function hostAccessList(): array
    {
        return array_values(array_filter(explode(',', (string) $this->host_access)));
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

    /**
     * Forgets every cached list, for every scope, and reloads this process's table.
     */
    public static function flush(): void
    {
        cache()->forever(self::CACHE_GENERATION, (int) cache()->get(self::CACHE_GENERATION, 0) + 1);

        self::$scopeNodeIds = null;

        self::refreshRows();
    }

    public function canEnable(): bool
    {
        return $this->isPlugin() && (bool) $this->can_enable;
    }

    public function canDisable(): bool
    {
        return $this->isPlugin() && (bool) $this->can_disable;
    }

    public function canConfigure(): bool
    {
        return $this->isPlugin() && (bool) $this->can_configure && $this->hasSettings();
    }

    public function canUpdate(): bool
    {
        return $this->isPlugin() && (bool) $this->can_update;
    }

    public function canUninstall(): bool
    {
        return $this->isPlugin() && (bool) $this->can_uninstall;
    }

    /** Wings plugins don't depend on the Panel's version, so $panelVersion (a Panel plugin check) is unused. */
    public function isCompatible(?string $panelVersion = null): bool
    {
        return $this->status !== PluginStatus::Incompatible;
    }

    /**
     * Whether the last update check found a newer version (A2 "update-check"). can_update only says the
     * plugin has an update URL and the node's level allows updating.
     */
    public function isUpdateAvailable(): bool
    {
        return $this->isPlugin() && ($this->lastUpdateCheck()['available'] ?? false) === true;
    }

    /** A plugin without an update URL has nothing to check (Wings omits update_url when empty). */
    public function canCheckForUpdate(): bool
    {
        return $this->isPlugin() && filled($this->update_url);
    }

    /** @return array{installed: ?string, latest: ?string, available: bool, error: ?string}|null */
    public function lastUpdateCheck(): ?array
    {
        $check = cache()->get($this->updateCheckKey());

        return is_array($check) ? $check : null;
    }

    private function updateCheckKey(): string
    {
        return self::CACHE_PREFIX . 'update_check.' . $this->node_id . '.' . sha1($this->plugin_id . "\0" . $this->version);
    }

    /**
     * Checks $plugins for updates at once and keeps the answers. Plugins without an update URL are skipped;
     * a node that refused or didn't answer gives a Throwable for that plugin.
     *
     * @param  iterable<WingsPlugin>  $plugins
     * @return array<string, array{installed: ?string, latest: ?string, available: bool, error: ?string}|Throwable> keyed by row id
     */
    public static function checkForUpdates(iterable $plugins): array
    {
        $results = [];
        $promises = [];
        $rows = [];

        foreach ($plugins as $plugin) {
            if (!$plugin->canCheckForUpdate()) {
                continue;
            }

            try {
                $promises[$plugin->id] = $plugin->repository()->updateCheckAsync($plugin->plugin_id);
                $rows[$plugin->id] = $plugin;
            } catch (Throwable $exception) {
                $results[$plugin->id] = $exception;
            }
        }

        foreach (Utils::settle($promises)->wait() as $id => $settled) {
            if ($settled['state'] !== PromiseInterface::FULFILLED) {
                $results[$id] = $settled['reason'] instanceof Throwable ? $settled['reason'] : new RuntimeException((string) $settled['reason']);

                continue;
            }

            cache()->put($rows[$id]->updateCheckKey(), $settled['value'], now()->addSeconds(self::UPDATE_CHECK_SECONDS));
            $results[$id] = $settled['value'];
        }

        return $results;
    }

    public function hasSettings(): bool
    {
        return $this->declaredSettings() !== [];
    }

    /**
     * Stored values, with declared defaults for the keys that have none.
     *
     * @return array<string, mixed>
     */
    public function getSettingsFormData(): array
    {
        $values = $this->settingValues();

        $data = [];
        foreach ($this->declaredSettings() as $setting) {
            $key = self::settingKey($setting);
            // Read-only settings show their stored value themselves, under a path of their own
            if ($key === null || !self::isEditableSetting($setting)) {
                continue;
            }

            if (array_key_exists($key, $values)) {
                $data[$key] = $values[$key];
            } elseif (array_key_exists('default', $setting)) {
                $data[$key] = $setting['default'];
            }
        }

        return $data;
    }

    /**
     * Built from the settings schema the plugin declares (A3.1).
     *
     * @return Component[]
     */
    public function getSettingsForm(): array
    {
        $fields = [];
        $values = $this->settingValues();

        foreach ($this->declaredSettings() as $index => $setting) {
            $key = self::settingKey($setting);
            if ($key === null) {
                continue;
            }

            $type = $setting['type'] ?? null;
            $secret = ($setting['secret'] ?? false) === true;
            $label = is_string($setting['label'] ?? null) && $setting['label'] !== '' ? $setting['label'] : $key;

            if (!self::isEditableSetting($setting)) {
                $shown = array_key_exists($key, $values) ? $values[$key] : ($setting['default'] ?? null);

                // A path no valid key can have (it starts with "-"), so a dotted key isn't read as nesting.
                // Never sent: settingsPayload() sends the stored value back.
                $fields[] = TextInput::make("-readonly-$index")
                    ->label($label)
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn () => $shown === null || is_scalar($shown) ? (string) $shown : json_encode($shown))
                    ->helperText(preg_match(self::SETTING_KEY_PATTERN, $key)
                        ? trans('admin/plugin.wings.setting_type_unsupported', ['type' => is_string($type) ? $type : '?'])
                        : trans('admin/plugin.wings.setting_key_unsupported', ['key' => $key]));

                continue;
            }

            $field = match ($type) {
                'boolean' => Toggle::make($key)->inline(false),
                'number' => TextInput::make($key)->numeric(),
                'select' => Select::make($key)
                    ->options(collect(is_array($setting['options'] ?? null) ? $setting['options'] : [])
                        ->filter(fn ($option) => is_array($option) && is_scalar($option['value'] ?? null))
                        ->mapWithKeys(fn (array $option): array => [(string) $option['value'] => (string) ($option['label'] ?? $option['value'])])
                        ->all()),
                default => TextInput::make($key)->password($secret)->revealable($secret),
            };

            $description = is_string($setting['description'] ?? null) ? $setting['description'] : null;
            if ($secret) {
                $description = trim(($description ?? '') . ' ' . trans('admin/plugin.wings.setting_secret_hint'));
            }

            $fields[] = $field
                ->label($label)
                ->helperText($description)
                ->required(($setting['required'] ?? false) === true && !array_key_exists('default', $setting) && $type !== 'boolean');
        }

        return $fields;
    }

    /**
     * Turns the submitted form into the values object Wings expects (A3.1): numbers as JSON numbers,
     * select values as the option's own value, an unchanged secret as "********" (Wings keeps it), and
     * keys without a value left out (Wings unsets them, so the default applies).
     *
     * @param  array<mixed, mixed>  $data
     * @return array<string, mixed>
     */
    public function settingsPayload(array $data): array
    {
        $stored = $this->settingValues();
        $payload = [];

        foreach ($this->declaredSettings() as $setting) {
            $key = self::settingKey($setting);
            if ($key === null) {
                continue;
            }

            $type = $setting['type'] ?? null;

            // A type or key the Panel can't edit keeps its stored value: Wings unsets any key left out
            if (!self::isEditableSetting($setting)) {
                if (array_key_exists($key, $stored)) {
                    $payload[$key] = $stored[$key];
                }

                continue;
            }

            $value = $data[$key] ?? null;

            $value = match ($type) {
                'boolean' => $value === null ? null : (bool) $value,
                'number' => self::toNumber($value),
                'select' => self::toOption($setting, $value),
                default => is_scalar($value) && $value !== '' ? (string) $value : null,
            };

            if ($value === null) {
                continue;
            }

            // A default the admin didn't change stays a default, so a newer default still applies
            if (!array_key_exists($key, $stored) && array_key_exists('default', $setting) && $value === $setting['default']) {
                continue;
            }

            $payload[$key] = $value;
        }

        return $payload;
    }

    /** @param  array<mixed>  $setting */
    private static function settingKey(array $setting): ?string
    {
        $key = $setting['key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Whether the Panel can edit this setting: a type it knows (A3.1) and a key Wings accepts, which the
     * form can use as a field name as it is.
     *
     * @param  array<mixed>  $setting
     */
    public static function isEditableSetting(array $setting): bool
    {
        $key = self::settingKey($setting);

        return $key !== null
            && self::isValidSettingKey($key)
            && in_array($setting['type'] ?? null, self::SETTING_TYPES, true);
    }

    public static function isValidSettingKey(mixed $key): bool
    {
        return is_string($key) && preg_match(self::SETTING_KEY_PATTERN, $key) === 1;
    }

    private static function toNumber(mixed $value): int|float|string|null
    {
        if (is_int($value)) {
            return $value;
        }

        // Filament's numeric input gives 3.0 for "3"; both are the JSON number 3
        if (is_float($value)) {
            // Strictly below: PHP_INT_MAX as a float rounds up to 2^63, which (int) can't hold
            return is_finite($value) && floor($value) === $value && abs($value) < PHP_INT_MAX ? (int) $value : $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (!is_numeric($value)) {
            // Sent as it is, so Wings answers with a message for this field
            return $value;
        }

        // An integer string past the int range would silently become PHP_INT_MAX with (int)
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return $int !== false ? $int : (float) $value;
    }

    /** @param  array<mixed>  $setting */
    private static function toOption(array $setting, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach (is_array($setting['options'] ?? null) ? $setting['options'] : [] as $option) {
            if (is_array($option) && is_scalar($option['value'] ?? null) && (string) $option['value'] === (string) $value) {
                return $option['value'];
            }
        }

        return $value;
    }

    /**
     * @param  array<mixed, mixed>  $data
     */
    public function saveSettings(array $data): void
    {
        try {
            $this->repository()->saveSettings($this->plugin_id, $this->settingsPayload($data));
        } finally {
            self::flush();
        }
    }

    /**
     * What $node's registries offer (A2, A3.2), cached for a minute. The caller checks the node first: the
     * admin's access to it and its level ("signed").
     *
     * @return array{data: list<array{name: string, version: string, registry: string, author: ?string, description: ?string, capability: ?string, has_signature: bool, installed: ?string}>, errors: array<string, string>, failure: ?string}
     */
    public static function availableOn(Node $node): array
    {
        $key = self::CACHE_PREFIX . (int) cache()->get(self::CACHE_GENERATION, 0) . '.available.' . $node->id;

        $cached = cache()->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $available = (new DaemonPluginRepository())->setNode($node)->available();
        } catch (Throwable $exception) {
            if (!$exception instanceof RequestException) {
                report($exception);
            }

            // A Wings without this endpoint reads the path as a plugin named "available"
            $failure = $exception instanceof RequestException && $exception->response->notFound()
                ? trans('admin/plugin.wings.available_unsupported')
                : WingsPluginFailure::message($exception);

            return ['data' => [], 'errors' => [], 'failure' => $failure];
        }

        $available = [...$available, 'failure' => null];
        cache()->put($key, $available, now()->addMinute());

        return $available;
    }

    /**
     * Names of the accessible nodes, keyed by id, that listed their plugins at $level or higher.
     *
     * @return Collection<int, string>
     */
    public static function nodesAllowing(string $level, ?User $user = null): Collection
    {
        return collect(self::nodeStates($user))
            ->filter(fn (array $state) => $state['state'] === 'available' && DaemonPluginRepository::levelAllows($state['panel_access'], $level))
            ->map(fn (array $state) => $state['name']);
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
