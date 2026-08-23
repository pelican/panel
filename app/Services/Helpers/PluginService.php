<?php

namespace App\Services\Helpers;

use App\Enums\PluginStatus;
use App\Exceptions\Service\InvalidFileUploadException;
use App\Models\Plugin;
use Composer\Autoload\ClassLoader;
use Exception;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use JsonException;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use ZipArchive;

class PluginService
{
    public function __construct(private Application $app) {}

    public function loadPlugins(): void
    {
        // Don't load any plugins during tests
        if ($this->app->runningUnitTests()) {
            return;
        }

        /** @var ClassLoader $classLoader */
        $classLoader = File::getRequire(base_path('vendor/autoload.php'));

        $plugins = Plugin::orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            try {
                // Filter out plugins that are not compatible with the current panel version
                if (!$plugin->isCompatible()) {
                    $this->setStatus($plugin, PluginStatus::Incompatible, 'This Plugin is only compatible with Panel version ' . $plugin->panel_version . (!$plugin->isPanelVersionStrict() ? ' or newer' : '') . ' but you are using version ' . config('app.version') . '!');

                    continue;
                } else {
                    // Make sure to update the status if a plugin is no longer incompatible (e.g. because the user changed their panel version)
                    if ($plugin->status === PluginStatus::Incompatible) {
                        $this->disablePlugin($plugin);
                    }
                }

                if ($plugin->namespace === 'Error') {
                    continue;
                }

                // Always autoload src directory to make sure all class names can be resolved (e.g. in migrations)
                $namespace = $plugin->namespace . '\\';
                if (!array_key_exists($namespace, $classLoader->getPrefixesPsr4())) {
                    $classLoader->setPsr4($namespace, plugin_path($plugin->id, 'src/'));

                    $classLoader->addPsr4('Database\Factories\\', plugin_path($plugin->id, 'database/Factories/'));
                    $classLoader->addPsr4('Database\Seeders\\', plugin_path($plugin->id, 'database/Seeders/'));
                }

                // Load config
                $config = plugin_path($plugin->id, 'config', $plugin->id . '.php');
                if (file_exists($config)) {
                    config()->set($plugin->id, require $config);
                }

                // Filter out plugins that should not be loaded (e.g. because they are disabled or not installed yet)
                if (!$plugin->shouldLoad()) {
                    continue;
                }

                // Load translations
                $translations = plugin_path($plugin->id, 'lang');
                if (file_exists($translations)) {
                    $this->app->afterResolving('translator', function ($translator) use ($plugin, $translations) {
                        if ($plugin->isLanguage()) {
                            $translator->addPath($translations);
                        } else {
                            $translator->addNamespace($plugin->id, $translations);
                        }
                    });
                }

                // Register service providers
                foreach ($plugin->getProviders() as $provider) {
                    if (!class_exists($provider) || !is_subclass_of($provider, ServiceProvider::class)) {
                        continue;
                    }

                    $this->app->register($provider);
                }

                // Resolve artisan commands
                foreach ($plugin->getCommands() as $command) {
                    if (!class_exists($command) || !is_subclass_of($command, Command::class)) {
                        continue;
                    }

                    ConsoleApplication::starting(function ($artisan) use ($command) {
                        $artisan->resolve($command);
                    });
                }

                // Load migrations
                $migrations = plugin_path($plugin->id, 'database', 'migrations');
                if (file_exists($migrations)) {
                    $this->app->afterResolving('migrator', function ($migrator) use ($migrations) {
                        $migrator->path($migrations);
                    });
                }

                // Load views
                $views = plugin_path($plugin->id, 'resources', 'views');
                if (file_exists($views)) {
                    $this->app->afterResolving('view', function ($view) use ($plugin, $views) {
                        $view->addNamespace($plugin->id, $views);
                    });
                }
            } catch (Exception $exception) {
                $this->handlePluginException($plugin, $exception);
            }
        }
    }

    public function loadPanelPlugins(Panel $panel): void
    {
        // Don't load any plugins during tests
        if ($this->app->runningUnitTests()) {
            return;
        }

        $plugins = Plugin::orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            try {
                if (!$plugin->shouldLoad($panel->getId())) {
                    continue;
                }

                $pluginClass = $plugin->fullClass();

                throw_unless(class_exists($pluginClass), new Exception('Class "' . $pluginClass . '" not found'));

                $panel->plugin(new $pluginClass());

                if ($plugin->status === PluginStatus::Errored) {
                    $this->enablePlugin($plugin);
                }
            } catch (Exception $exception) {
                $this->handlePluginException($plugin, $exception);
            }
        }
    }

    /**
     * @param  null|array<string, string>  $newPackages
     * @param  null|array<string, string>  $oldPackages
     *
     * @throws Exception
     */
    public function manageComposerPackages(?array $newPackages = [], ?array $oldPackages = null): void
    {
        $newPackages ??= [];

        $plugins = Plugin::orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            if (!$plugin->composer_packages) {
                continue;
            }

            if (!$plugin->shouldLoad()) {
                continue;
            }

            try {
                $pluginPackages = json_decode($plugin->composer_packages, true, 512, JSON_THROW_ON_ERROR);

                $newPackages = array_merge($newPackages, $pluginPackages);
            } catch (Exception $exception) {
                report($exception);
            }
        }

        $oldPackages = collect($oldPackages)
            ->filter(fn ($version, $package) => !array_key_exists($package, $newPackages))
            ->keys()
            ->unique()
            ->toArray();

        if (count($oldPackages) > 0) {
            $result = Process::path(base_path())->timeout(600)->run(['composer', 'remove', ...$oldPackages]);
            throw_if($result->failed(), new Exception('Could not remove old composer packages: ' . $result->errorOutput()));
        }

        $newPackages = collect($newPackages)
            ->map(fn ($version, $package) => "$package:$version")
            ->flatten()
            ->unique()
            ->toArray();

        if (count($newPackages) > 0) {
            $result = Process::path(base_path())->timeout(600)->run(['composer', 'require', ...$newPackages]);
            throw_if($result->failed(), new Exception('Could not require new composer packages: ' . $result->errorOutput()));
        }
    }

    /** @throws Exception */
    public function runPluginMigrations(Plugin $plugin): void
    {
        $migrations = plugin_path($plugin->id, 'database', 'migrations');
        if (file_exists($migrations)) {
            try {
                $migrator = $this->app->make(Migrator::class);
                $migrator->run($migrations);
            } catch (Exception $exception) {
                throw new Exception("Could not run migrations': " . $exception->getMessage());
            }
        }
    }

    /** @throws Exception */
    public function rollbackPluginMigrations(Plugin $plugin): void
    {
        $migrations = plugin_path($plugin->id, 'database', 'migrations');
        if (file_exists($migrations)) {
            try {
                $migrator = $this->app->make(Migrator::class);
                $migrator->reset($migrations);
            } catch (Exception $exception) {
                throw new Exception("Could not rollback migrations': " . $exception->getMessage());
            }
        }
    }

    /** @throws Exception */
    public function runPluginSeeder(Plugin $plugin): void
    {
        $seeder = $plugin->getSeeder();
        if ($seeder) {
            try {
                $seederObject = $this->app->make($seeder)->setContainer($this->app);

                Model::unguarded(fn () => $seederObject->__invoke());
            } catch (Exception $exception) {
                throw new Exception('Could not run seeder: ' . $exception->getMessage());
            }
        }
    }

    public function buildAssets(bool $throw = false): bool
    {
        try {
            $result = Process::path(base_path())->timeout(300)->run('yarn install');
            throw_if($result->failed(), new Exception('Could not install yarn dependencies: ' . $result->errorOutput()));

            $result = Process::path(base_path())->timeout(600)->run('yarn build');
            throw_if($result->failed(), new Exception('Could not build assets: ' . $result->errorOutput()));

            return true;
        } catch (Exception $exception) {
            throw_if($throw || $this->isDevModeActive(), ($exception));

            Log::warning($exception->getMessage(), ['exception' => $exception]);
        }

        return false;
    }

    /** @throws Exception */
    public function installPlugin(Plugin $plugin, bool $enable = true): void
    {
        try {
            $this->manageComposerPackages(json_decode($plugin->composer_packages, true, 512));

            $this->buildAssets($plugin->isTheme());

            $this->runPluginMigrations($plugin);

            $this->runPluginSeeder($plugin);

            if ($enable) {
                $this->enablePlugin($plugin);
            } else {
                if ($plugin->status === PluginStatus::NotInstalled) {
                    $this->disablePlugin($plugin);
                }
            }

            foreach (Filament::getPanels() as $panel) {
                $panel->clearCachedComponents();
            }
        } catch (Exception $exception) {
            $this->setStatus($plugin, PluginStatus::NotInstalled, $exception->getMessage());

            throw $exception;
        }
    }

    /** @throws Exception */
    public function updatePlugin(Plugin $plugin): void
    {
        $downloadUrl = $plugin->getDownloadUrlForUpdate();
        throw_unless($downloadUrl, new Exception('No download url found.'));

        $this->downloadPluginFromUrl($downloadUrl, $plugin->id);

        Plugin::refreshRows();
        $plugin = $plugin->refresh();

        $this->installPlugin($plugin, false);

        cache()->forget("plugins.$plugin->id.update");
    }

    /** @throws Exception */
    public function uninstallPlugin(Plugin $plugin, bool $deleteFiles = false): void
    {
        $pluginPackages = json_decode($plugin->composer_packages, true, 512);

        $this->rollbackPluginMigrations($plugin);

        $this->setStatus($plugin, PluginStatus::NotInstalled);

        if ($deleteFiles) {
            $this->deletePlugin($plugin);
        }

        $this->buildAssets();

        $this->manageComposerPackages(oldPackages: $pluginPackages);

        foreach (Filament::getPanels() as $panel) {
            $panel->clearCachedComponents();
        }
    }

    /** @throws Exception */
    public function downloadPluginFromFile(UploadedFile $file, ?string $expectedId = null): string
    {
        // Validate file size to prevent zip bombs
        $maxSize = config('panel.plugin.max_import_size');
        $maxSizeLabel = round($maxSize / 1024 / 1024, 2);
        throw_if($file->getSize() > $maxSize, new Exception("Zip file too large. ($maxSizeLabel MiB)"));

        $zip = new ZipArchive();

        throw_unless($zip->open($file->getPathname()), new Exception('Could not open zip file.'));

        // The check above only sees the compressed bytes, which say nothing about what the
        // archive expands to — a small, highly compressible zip can still fill the plugins
        // volume. Total the uncompressed entry sizes while walking the entries and bail as
        // soon as they exceed the same limit, before anything is written to disk.
        $uncompressedSize = 0;

        // Validate zip contents before extraction
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (Str::contains($filename, '..') || Str::startsWith($filename, '/')) {
                $zip->close();
                throw new Exception('Zip file contains invalid path traversal sequences.');
            }

            $stat = $zip->statIndex($i) ?: [];
            $uncompressedSize += $stat['size'] ?? 0;

            if ($uncompressedSize > $maxSize) {
                $zip->close();
                throw new Exception("Zip file contents too large. ($maxSizeLabel MiB)");
            }
        }

        // Extract into a temporary directory that lives on the same filesystem as the plugins
        // directory (named as a dotfile so plugin discovery ignores it). Deriving the id from
        // the extracted plugin.json — rather than the upload filename — means a renamed zip
        // still imports, and keeping temp + target on one volume makes every move below an
        // atomic same-filesystem rename instead of a cross-device copy that can fail.
        $tmpDir = (new TemporaryDirectory(base_path('plugins')))
            ->name('.import-' . Str::random(16))
            ->deleteWhenDestroyed()
            ->create();

        $extractPath = $tmpDir->path('contents');

        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new Exception('Could not extract zip file.');
        }

        $zip->close();

        // Locate plugin.json, either at the archive root (flat zip) or one level deep
        // (single top-level folder). Prefer the shallowest match.
        $manifest = $this->locatePluginManifest($extractPath);
        throw_if($manifest === null, new Exception(trans('admin/plugin.notifications.import_no_manifest')));

        $data = File::json($manifest, JSON_THROW_ON_ERROR);
        $id = $data['id'] ?? null;
        throw_if(!is_string($id) || trim($id) === '', new Exception(trans('admin/plugin.notifications.import_no_manifest')));

        // The id becomes the install directory name and must equal it once installed (see
        // Plugin::getRows()), so guard against anything that isn't a safe slug — e.g. an id of
        // "../../evil" that would escape the plugins directory when passed to plugin_path().
        $pluginName = Str::lower(trim($id));
        throw_unless(preg_match('/^[a-z0-9][a-z0-9._-]*$/', $pluginName), new Exception(trans('admin/plugin.notifications.import_invalid_id')));

        // When updating a known plugin, the archive must be for that same plugin — reject a
        // mismatched id before moving anything, so an update can't overwrite a different plugin.
        throw_if($expectedId !== null && $pluginName !== $expectedId, new Exception(trans('admin/plugin.notifications.import_id_mismatch', ['expected' => $expectedId, 'actual' => $pluginName])));

        $target = plugin_path($pluginName);
        $source = dirname($manifest);

        // Importing over an existing plugin replaces it. Set the current install aside as a
        // rollback until the new copy is in place, so a failed move can't leave nothing behind.
        // The backup is a dotfile so discovery ignores it during the swap; temp, target and
        // backup all sit under plugins/, so each move is a same-filesystem rename.
        $rollback = null;
        if (File::isDirectory($target)) {
            $rollback = plugin_path('.' . $pluginName . '.bak');
            File::deleteDirectory($rollback);
            throw_unless(File::moveDirectory($target, $rollback), new Exception('Could not set the existing plugin aside.'));
        }

        // Move the folder containing plugin.json to plugins/<id> so the layout is correct
        // regardless of the archive's internal folder name.
        if (!File::moveDirectory($source, $target)) {
            if ($rollback !== null) {
                File::moveDirectory($rollback, $target);
            }

            throw new Exception('Could not move plugin into place.');
        }

        if ($rollback !== null) {
            File::deleteDirectory($rollback);
        }

        return $pluginName;
    }

    /**
     * Find a plugin.json inside an extracted archive, either at its root or within a single
     * top-level directory. Returns the absolute path to the manifest, or null if none exists.
     */
    private function locatePluginManifest(string $path): ?string
    {
        $rootManifest = join_paths($path, 'plugin.json');
        if (File::exists($rootManifest)) {
            return $rootManifest;
        }

        foreach (File::directories($path) as $directory) {
            $manifest = join_paths($directory, 'plugin.json');
            if (File::exists($manifest)) {
                return $manifest;
            }
        }

        return null;
    }

    /** @throws Exception */
    public function downloadPluginFromUrl(string $url, ?string $expectedId = null): string
    {
        $basename = pathinfo($url, PATHINFO_BASENAME);
        $tmpDir = TemporaryDirectory::make()->deleteWhenDestroyed();
        $tmpPath = $tmpDir->path($basename);

        $content = Http::timeout(60)->connectTimeout(5)->throw()->get($url)->body();

        // Validate file size to prevent zip bombs
        $maxSize = config('panel.plugin.max_import_size');
        $maxSizeLabel = round($maxSize / 1024 / 1024, 2);
        throw_if(strlen($content) > $maxSize, new InvalidFileUploadException("Zip file too large. ($maxSizeLabel MiB)"));

        throw_unless(file_put_contents($tmpPath, $content), new InvalidFileUploadException('Could not write temporary file.'));

        return $this->downloadPluginFromFile(new UploadedFile($tmpPath, $basename, 'application/zip'), $expectedId);
    }

    public function deletePlugin(Plugin $plugin): void
    {
        File::deleteDirectory(plugin_path($plugin->id));
    }

    public function enablePlugin(string|Plugin $plugin): void
    {
        $this->setStatus($plugin, PluginStatus::Enabled);
    }

    public function disablePlugin(string|Plugin $plugin): void
    {
        $this->setStatus($plugin, PluginStatus::Disabled);
    }

    /** @param array<string, mixed> $data */
    private function setMetaData(string|Plugin $plugin, array $data): void
    {
        $path = plugin_path($plugin->id, 'plugin.json');

        if (File::exists($path)) {
            $pluginData = File::json($path, JSON_THROW_ON_ERROR);
            $metaData = array_merge($pluginData['meta'] ?? [], $data);
            $pluginData['meta'] = $metaData;

            File::put($path, json_encode($pluginData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $plugin->update($metaData);
        }
    }

    private function setStatus(string|Plugin $plugin, PluginStatus $status, ?string $message = null): void
    {
        $this->setMetaData($plugin, [
            'status' => $status,
            'status_message' => $message,
        ]);
    }

    /**
     * @param  array<int, string>  $order
     *
     * @throws JsonException
     */
    public function updateLoadOrder(array $order): void
    {
        foreach ($order as $i => $plugin) {
            $plugin = Plugin::firstOrFail(str($plugin)->lower()->toString());

            $this->setMetaData($plugin, [
                'load_order' => $i,
            ]);
        }
    }

    public function hasThemePluginEnabled(): bool
    {
        $plugins = Plugin::orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            if ($plugin->isTheme() && $plugin->status === PluginStatus::Enabled) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    public function getPluginLanguages(): array
    {
        $languages = [];

        $plugins = Plugin::orderBy('load_order')->get();
        foreach ($plugins as $plugin) {
            if ($plugin->status !== PluginStatus::Enabled || !$plugin->isLanguage()) {
                continue;
            }

            $languages = array_merge($languages, collect(File::directories(plugin_path($plugin->id, 'lang')))->map(fn ($path) => basename($path))->toArray());
        }

        return array_unique($languages);
    }

    public function isDevModeActive(): bool
    {
        return config('panel.plugin.dev_mode', false);
    }

    private function handlePluginException(Plugin $plugin, Exception $exception): void
    {
        throw_if($this->isDevModeActive(), ($exception));

        report($exception);

        $this->setStatus($plugin, PluginStatus::Errored, $exception->getMessage());
    }
}
