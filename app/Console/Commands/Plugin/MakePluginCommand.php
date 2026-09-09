<?php

namespace App\Console\Commands\Plugin;

use App\Enums\PluginCategory;
use App\Enums\PluginStatus;
use App\Services\Helpers\SoftwareVersionService;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakePluginCommand extends Command
{
    protected $signature = 'p:plugin:make
                            {--name=}
                            {--author=}
                            {--description=}
                            {--category=}
                            {--url=}
                            {--updateUrl=}
                            {--panels=}
                            {--panelVersion=}';

    protected $description = 'Create a new plugin';

    public function __construct(private Filesystem $filesystem)
    {
        parent::__construct();
    }

    public function handle(SoftwareVersionService $versionService): void
    {
        $name = $this->option('name') ?? $this->ask('Name');
        $name = preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($name));

        $id = Str::slug($name);

        if ($this->filesystem->exists(plugin_path($id))) {
            $this->error('Plugin with that name already exists!');

            return;
        }

        $author = $this->option('author') ?? $this->ask('Author', cache('plugin.author'));
        $author = preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($author));
        cache()->forever('plugin.author', $author);

        $namespace = Str::studly($author) . '\\' . Str::studly($name);
        $class = Str::studly($name . 'Plugin');

        if (class_exists('\\' . $namespace . '\\' . $class)) {
            $this->error('Plugin class with that name already exists!');

            return;
        }

        $this->info('Creating Plugin "' . $name . '" (' . $id . ') by ' . $author);

        $description = $this->option('description') ?? $this->ask('Description (can be empty)');

        $category = $this->option('category') ?? $this->choice('Category', collect(PluginCategory::cases())->mapWithKeys(fn (PluginCategory $category) => [$category->value => $category->getLabel()])->toArray(), PluginCategory::Plugin->value);

        if (!PluginCategory::tryFrom($category)) {
            $this->error('Unknown plugin category!');

            return;
        }

        $url = $this->option('url') ?? $this->ask('URL (can be empty)');
        $updateUrl = $this->option('updateUrl') ?? $this->ask('Update URL (can be empty)');

        $panels = $this->option('panels');
        if (!$panels) {
            if ($this->confirm('Should the plugin be available on all panels?', true)) {
                $panels = null;
            } else {
                $panels = $this->choice('Panels (comma separated list)', [
                    'admin' => 'Admin Area',
                    'server' => 'Client Area',
                    'app' => 'Server List',
                ], multiple: true);
            }
        }
        $panels = is_string($panels) ? explode(',', $panels) : $panels;

        $panelVersion = $this->option('panelVersion');
        if (!$panelVersion) {
            $panelVersion = $this->ask('Required panel version (leave empty for no constraint)', $versionService->currentComparableVersion());

            if ($panelVersion && $this->confirm("Should the version constraint be minimal instead of strict? ($panelVersion or higher instead of only $panelVersion)")) {
                $panelVersion = "^$panelVersion";
            }
        }

        $composerPackages = null;
        // TODO: ask for composer packages?

        // Create base directory
        $this->filesystem->makeDirectory(plugin_path($id));

        // Write plugin.json
        $this->filesystem->put(plugin_path($id, 'plugin.json'), json_encode([
            'id' => $id,
            'name' => $name,
            'author' => $author,
            'version' => '1.0.0',
            'description' => $description,
            'category' => $category,
            'url' => $url,
            'update_url' => $updateUrl,
            'namespace' => $namespace,
            'class' => $class,
            'panels' => $panels,
            'panel_version' => $panelVersion,
            'composer_packages' => $composerPackages,
            'meta' => [
                'status' => PluginStatus::Enabled,
                'status_message' => null,
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Create src directory and create main class
        $this->filesystem->makeDirectory(plugin_path($id, 'src'));
        $this->filesystem->put(plugin_path($id, 'src', $class . '.php'), Str::replace(['$namespace$', '$class$', '$id$'], [$namespace, $class, $id], file_get_contents(__DIR__ . '/Plugin.stub')));

        // Create Providers directory and create service provider
        $this->filesystem->makeDirectory(plugin_path($id, 'src', 'Providers'));
        $this->filesystem->put(plugin_path($id, 'src', 'Providers', $class . 'Provider.php'), Str::replace(['$namespace$', '$class$'], [$namespace, $class], file_get_contents(__DIR__ . '/PluginProvider.stub')));

        // Create config directory and create config file
        $this->filesystem->makeDirectory(plugin_path($id, 'config'));
        $this->filesystem->put(plugin_path($id, 'config', $id . '.php'), Str::replace(['$name$'], [$name], file_get_contents(__DIR__ . '/PluginConfig.stub')));

        $this->info('Plugin created.');
    }
}
