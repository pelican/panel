<?php

namespace App\Models;

use App\Contracts\Plugins\HasPluginSettings;
use App\Enums\PluginCategory;
use App\Enums\PluginStatus;
use App\Exceptions\PluginIdMismatchException;
use App\Facades\Plugins;
use Exception;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Sushi\Sushi;

/**
 * @property string $id
 * @property string $name
 * @property string $author
 * @property string $version
 * @property string|null $description
 * @property PluginCategory $category
 * @property string|null $url
 * @property string|null $update_url
 * @property string $namespace
 * @property string $class
 * @property string|null $panels
 * @property string|null $panel_version
 * @property string|null $composer_packages
 * @property PluginStatus $status
 * @property string|null $status_message
 * @property int $load_order
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereAuthor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereComposerPackages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereLoadOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereNamespace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin wherePanelVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin wherePanels($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereStatusMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereUpdateUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Plugin whereVersion($value)
 */
class Plugin extends Model implements HasPluginSettings
{
    use Sushi;

    public const RESOURCE_NAME = 'plugin';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'status',
        'status_message',
        'load_order',
    ];

    /** @return string[] */
    public function getSchema(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'author' => 'string',
            'version' => 'string',
            'description' => 'string',
            'category' => 'string',
            'url' => 'string',
            'update_url' => 'string',
            'namespace' => 'string',
            'class' => 'string',
            'panels' => 'string',
            'panel_version' => 'string',
            'composer_packages' => 'string',
            'status' => 'string',
            'status_message' => 'string',
            'load_order' => 'integer',
        ];
    }

    /**
     * @return array<array{
     *     id: string,
     *     name: string,
     *     author: string,
     *     version: string,
     *     description: ?string,
     *     category: string,
     *     url: ?string,
     *     update_url: ?string,
     *     namespace: string,
     *     class: string,
     *     panels: ?string,
     *     panel_version: ?string,
     *     composer_packages: ?string,
     *     status: string,
     *     status_message: ?string,
     *     load_order: int
     * }>
     */
    public function getRows(): array
    {
        $plugins = [];

        $directories = File::directories(base_path('plugins/'));
        foreach ($directories as $directory) {
            $plugin = File::basename($directory);

            $path = plugin_path($plugin, 'plugin.json');
            if (!file_exists($path)) {
                continue;
            }

            try {
                $data = File::json($path, JSON_THROW_ON_ERROR);
                $data['id'] = Str::lower($data['id']);

                throw_if($data['id'] !== Str::lower($plugin), new PluginIdMismatchException("Plugin id mismatch for folder name ($plugin) and id in plugin.json ({$data['id']})!"));

                $panels = null;
                if (array_key_exists('panels', $data)) {
                    $panels = $data['panels'];
                    $panels = is_array($panels) ? implode(',', $panels) : $panels;
                }

                $composerPackages = null;
                if (array_key_exists('composer_packages', $data)) {
                    $composerPackages = $data['composer_packages'];
                    $composerPackages = is_array($composerPackages) ? json_encode($composerPackages, JSON_THROW_ON_ERROR) : $composerPackages;
                }

                $data = [
                    'id' => $data['id'],
                    'name' => $data['name'],
                    'author' => $data['author'],
                    'version' => Arr::get($data, 'version', '1.0.0'),
                    'description' => Arr::get($data, 'description', null),
                    'category' => $data['category'],
                    'url' => Arr::get($data, 'url', null),
                    'update_url' => Arr::get($data, 'update_url', null),
                    'namespace' => $data['namespace'],
                    'class' => $data['class'],
                    'panels' => $panels,
                    'panel_version' => Arr::get($data, 'panel_version', null),
                    'composer_packages' => $composerPackages,

                    'status' => Str::lower(Arr::get($data, 'meta.status', PluginStatus::NotInstalled->value)),
                    'status_message' => Arr::get($data, 'meta.status_message', null),
                    'load_order' => Arr::integer($data, 'meta.load_order', 0),
                ];

                $plugins[] = $data;
            } catch (Exception $exception) {
                throw_if(config('panel.plugin.dev_mode', false), ($exception));

                report($exception);

                if (!$exception instanceof JsonException) {
                    $plugins[] = [
                        'id' => $exception instanceof PluginIdMismatchException ? $plugin : ($data['id'] ?? Str::uuid()),
                        'name' => $data['name'] ?? Str::headline($plugin),
                        'author' => $data['author'] ?? 'Unknown',
                        'version' => $data['version'] ?? '0.0.0',
                        'description' => $exception instanceof PluginIdMismatchException ? $exception->getMessage() : 'Plugin.json is invalid!',
                        'category' => PluginCategory::Plugin->value,
                        'url' => $data['url'] ?? null,
                        'update_url' => null,
                        'namespace' => 'Error',
                        'class' => 'Error',
                        'panels' => null,
                        'panel_version' => null,
                        'composer_packages' => null,

                        'status' => PluginStatus::Errored->value,
                        'status_message' => $exception->getMessage(),
                        'load_order' => 0,
                    ];
                }
            }
        }

        return $plugins;
    }

    protected function casts(): array
    {
        return [
            'status' => PluginStatus::class,
            'category' => PluginCategory::class,
        ];
    }

    public function fullClass(): string
    {
        return '\\' . $this->namespace . '\\' . $this->class;
    }

    public function shouldLoad(?string $panelId = null): bool
    {
        return $this->fullClass() !== '\\Error\\Error' && ($this->status === PluginStatus::Enabled || $this->status === PluginStatus::Errored) && (is_null($panelId) || !$this->panels || in_array($panelId, explode(',', $this->panels)));
    }

    public function canEnable(): bool
    {
        return $this->status === PluginStatus::Disabled && $this->isCompatible();
    }

    public function canDisable(): bool
    {
        return $this->status === PluginStatus::Enabled || $this->status === PluginStatus::Incompatible;
    }

    public function isCompatible(): bool
    {
        $currentPanelVersion = config('app.version', 'canary');

        return !$this->panel_version || $currentPanelVersion === 'canary' || version_compare($currentPanelVersion, str($this->panel_version)->trim('^'), $this->isPanelVersionStrict() ? '=' : '>=');
    }

    public function isPanelVersionStrict(): bool
    {
        if (!$this->panel_version) {
            return false;
        }

        return !str($this->panel_version)->startsWith('^');
    }

    public function isTheme(): bool
    {
        return $this->category === PluginCategory::Theme;
    }

    public function isLanguage(): bool
    {
        return $this->category === PluginCategory::Language;
    }

    /** @return null|array<string, array{version: string, download_url: string}> */
    private function getUpdateData(): ?array
    {
        if (!$this->update_url) {
            return null;
        }

        return cache()->remember("plugins.$this->id.update", now()->addMinutes(10), function () {
            try {
                $data = Http::timeout(5)->connectTimeout(1)->get($this->update_url)->throw()->json();

                // Support update jsons that cover multiple plugins
                if (array_key_exists($this->id, $data)) {
                    $data = $data[$this->id];
                }

                return $data;
            } catch (Exception $exception) {
                report($exception);
            }

            return [];
        });
    }

    public function isUpdateAvailable(): bool
    {
        $panelVersion = config('app.version', 'canary');

        if ($panelVersion === 'canary') {
            return false;
        }

        $updateData = $this->getUpdateData();
        if ($updateData) {
            if (array_key_exists($panelVersion, $updateData)) {
                return version_compare($updateData[$panelVersion]['version'], $this->version, '>');
            }

            if (array_key_exists('*', $updateData)) {
                return version_compare($updateData['*']['version'], $this->version, '>');
            }
        }

        return false;
    }

    public function getDownloadUrlForUpdate(): ?string
    {
        $panelVersion = config('app.version', 'canary');

        if ($panelVersion === 'canary') {
            return null;
        }

        $updateData = $this->getUpdateData();
        if ($updateData) {
            if (array_key_exists($panelVersion, $updateData)) {
                return $updateData[$panelVersion]['download_url'];
            }

            if (array_key_exists('*', $updateData)) {
                return $updateData['*']['download_url'];
            }
        }

        return null;
    }

    public function hasSettings(): bool
    {
        try {
            $pluginObject = new ($this->fullClass());

            return $pluginObject instanceof HasPluginSettings;
        } catch (Exception) {
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function getSettingsFormData(): array
    {
        try {
            $pluginObject = new ($this->fullClass());

            if ($pluginObject instanceof HasPluginSettings) {
                return $pluginObject->getSettingsFormData();
            }
        } catch (Exception) {
        }

        return [];
    }

    /** @return Component[] */
    public function getSettingsForm(): array
    {
        try {
            $pluginObject = new ($this->fullClass());

            if ($pluginObject instanceof HasPluginSettings) {
                return $pluginObject->getSettingsForm();
            }
        } catch (Exception) {
        }

        return [];
    }

    /** @param  array<mixed, mixed>  $data */
    public function saveSettings(array $data): void
    {
        try {
            $pluginObject = new ($this->fullClass());

            if ($pluginObject instanceof HasPluginSettings) {
                $pluginObject->saveSettings($data);
            }
        } catch (Exception) {
        }
    }

    /** @return string[] */
    public function getProviders(): array
    {
        $path = plugin_path($this->id, 'src', 'Providers');

        if (File::missing($path)) {
            return [];
        }

        return array_map(fn ($provider) => $this->namespace . '\\Providers\\' . str($provider->getRelativePathname())->remove('.php', false), File::allFiles($path));
    }

    /** @return string[] */
    public function getCommands(): array
    {
        $path = plugin_path($this->id, 'src', 'Console', 'Commands');

        if (File::missing($path)) {
            return [];
        }

        return array_map(fn ($provider) => $this->namespace . '\\Console\\Commands\\' . str($provider->getRelativePathname())->remove('.php', false), File::allFiles($path));
    }

    public function getSeeder(): ?string
    {
        $name = Str::studly($this->name);
        $seeder = "\Database\Seeders\\{$name}Seeder";

        return class_exists($seeder) ? $seeder : null;
    }

    public function getReadme(): ?string
    {
        return cache()->remember("plugins.$this->id.readme", now()->addMinutes(5), function () {
            $path = plugin_path($this->id, 'README.md');

            if (File::missing($path)) {
                return null;
            }

            return File::get($path);
        });
    }

    public static function refreshRows(): void
    {
        unset(static::$booted[static::class], static::$bootedCallbacks[static::class]);

        static::$sushiConnection = null;
    }
}
