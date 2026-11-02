<?php

namespace App\Models;

use App\Enums\PluginStatus;
use App\Repositories\Daemon\DaemonPluginRepository;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Illuminate\Database\Connection;
use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;

/**
 * A plugin installed on a node's Wings daemon, listed next to the Panel's own plugins.
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

    public const CACHE_KEY = 'wings_plugins.rows';

    private const CACHE_SECONDS = 10;

    /**
     * Needs its own connection, Plugin's static one is shared with subclasses.
     *
     * @var Connection|null
     */
    protected static $sushiConnection;

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
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return cache()->remember(self::CACHE_KEY, now()->addSeconds(self::CACHE_SECONDS), function (): array {
            $nodes = Node::all();

            $promises = [];
            foreach ($nodes as $node) {
                $promises[$node->id] = (new DaemonPluginRepository())->setNode($node)->getPluginsAsync();
            }

            $results = Utils::settle($promises)->wait();

            $rows = [];
            foreach ($nodes as $node) {
                $result = $results[$node->id];

                // Unreachable nodes get an error row so they don't look empty
                if ($result['state'] !== PromiseInterface::FULFILLED) {
                    $reason = $result['reason'];

                    if ($reason instanceof Throwable) {
                        report($reason);
                    }

                    $rows[] = self::errorRow($node, $reason instanceof Throwable ? $reason->getMessage() : (string) $reason);

                    continue;
                }

                // No plugin support, or the subsystem is turned off
                if (!$result['value']['enabled']) {
                    continue;
                }

                foreach ($result['value']['data'] as $plugin) {
                    try {
                        $rows[] = self::row($node, $plugin);
                    } catch (Throwable $exception) {
                        // One malformed plugin shouldn't hide the others
                        report($exception);
                    }
                }
            }

            return $rows;
        });
    }

    /**
     * @param  array<string, mixed>  $plugin
     * @return array<string, mixed>
     */
    private static function row(Node $node, array $plugin): array
    {
        $meta = $plugin['meta'] ?? [];

        // An unknown status would throw in the enum cast
        $rawStatus = $meta['status'] ?? PluginStatus::NotInstalled->value;
        $status = (is_string($rawStatus) ? PluginStatus::tryFrom($rawStatus) : null) ?? PluginStatus::Errored;

        return [
            // Unique across nodes, as the same plugin is often installed on several
            'id' => $node->id . ':' . $plugin['id'],
            'node_id' => $node->id,
            'node_name' => $node->name,
            'plugin_id' => $plugin['id'],

            'name' => $plugin['name'] ?? $plugin['id'],
            'author' => $plugin['author'] ?? 'Unknown',
            'version' => $plugin['version'] ?? '0.0.0',
            'description' => $plugin['description'] ?? null,
            'category' => $plugin['category'] ?? 'plugin',
            'url' => Arr::get($plugin, 'url') ?: null,
            'update_url' => Arr::get($plugin, 'update_url') ?: null,

            'wings_version' => Arr::get($plugin, 'wings_version') ?: null,
            'api_version' => $plugin['api_version'] ?? null,
            'package' => $plugin['package'] ?? null,

            'status' => $status->value,
            'status_message' => Arr::get($meta, 'status_message') ?: null,
            'load_order' => Arr::integer($meta, 'load_order', 0),

            'hooks' => implode(',', Arr::get($meta, 'registered.hooks', []) ?? []),

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

    public function repository(): DaemonPluginRepository
    {
        $node = $this->node() ?? throw new RuntimeException('The node of this plugin no longer exists');

        return (new DaemonPluginRepository())->setNode($node);
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

    public static function flush(): void
    {
        cache()->forget(self::CACHE_KEY);

        self::refreshRows();
    }

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
        return $this->status !== PluginStatus::Incompatible;
    }

    public function isUpdateAvailable(): bool
    {
        return false;
    }

    public function hasSettings(): bool
    {
        return $this->declaredSettings() !== [];
    }

    /** @return array<string, mixed> */
    public function getSettingsFormData(): array
    {
        $defaults = [];
        foreach ($this->declaredSettings() as $setting) {
            if (isset($setting['key']) && array_key_exists('default', $setting)) {
                $defaults[$setting['key']] = $setting['default'];
            }
        }

        return array_replace($defaults, $this->settingValues());
    }

    /**
     * Built from the settings declared in the plugin's plugin.json.
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
                ->label(($setting['label'] ?? null) ?: $key)
                ->helperText($setting['description'] ?? null)
                ->required((bool) ($setting['required'] ?? false));
        }

        return $fields;
    }

    /**
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
