<?php

namespace App\Console\Commands\Plugin;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Services\Helpers\PluginRegistryService;
use App\Services\Helpers\PluginService;
use App\Services\Helpers\RegistryPlugin;
use Exception;
use Illuminate\Console\Command;

class InstallPluginCommand extends Command
{
    protected $signature = 'p:plugin:install
        {id? : A plugin in the plugins directory, or one from a registry: @namespace/name, https://host/registry.json#name, or a name listed by exactly one configured registry}
        {--force : Install a registry plugin without asking for confirmation}';

    protected $description = 'Installs a plugin';

    public function handle(PluginService $pluginService, PluginRegistryService $registries): void
    {
        $id = $this->argument('id') ?? $this->choice('Plugin', Plugin::pluck('name', 'id')->toArray());

        // A plugin already in the plugins directory always wins over a registry, so a panel without
        // registries behaves exactly as before.
        if (!$registries->isReference($id)) {
            $plugin = Plugin::find(str($id)->lower()->toString());

            if ($plugin || !$registries->hasRegistries()) {
                $this->installLocal($plugin, $pluginService);

                return;
            }
        }

        $this->installFromRegistry($id, $pluginService, $registries);
    }

    private function installLocal(?Plugin $plugin, PluginService $pluginService): void
    {
        if (!$plugin) {
            $this->error('Plugin does not exist!');

            return;
        }

        if ($plugin->status !== PluginStatus::NotInstalled) {
            $this->error('Plugin is already installed!');

            return;
        }

        try {
            $pluginService->installPlugin($plugin);

            $this->info('Plugin installed and enabled.');
        } catch (Exception $exception) {
            $this->error('Could not install plugin: ' . $exception->getMessage());
        }
    }

    private function installFromRegistry(string $reference, PluginService $pluginService, PluginRegistryService $registries): void
    {
        try {
            $entry = $registries->resolve($reference);
        } catch (Exception $exception) {
            $this->error($exception->getMessage());

            return;
        }

        Plugin::refreshRows();
        $installed = Plugin::find($entry->name);
        $wasInstalled = $installed !== null && $installed->status !== PluginStatus::NotInstalled;

        // A registry can never push code: the admin sees what this is and where it comes from first.
        $this->table(['', ''], $this->details($entry, $installed));

        // What the plugin can do once installed, said plainly (contract v3.2, S)
        $this->warn(trans('admin/plugin.registry.runs_in_panel'));

        if (!$this->option('force') && !$this->confirm('Install this plugin? It runs inside the panel, so only install plugins from authors you trust.')) {
            $this->info('Nothing was installed.');

            return;
        }

        try {
            $id = $pluginService->downloadPluginFromRegistry($entry);

            Plugin::refreshRows();
            $plugin = Plugin::findOrFail($id);

            // Like an update, a plugin that was installed keeps being enabled or disabled as it was.
            $pluginService->installPlugin($plugin, !$wasInstalled);

            $this->info($wasInstalled ? 'Plugin updated.' : 'Plugin installed and enabled.');
        } catch (Exception $exception) {
            $this->error('Could not install plugin: ' . $exception->getMessage());
        }
    }

    /** @return array<int, array{string, string}> */
    private function details(RegistryPlugin $entry, ?Plugin $installed): array
    {
        $rows = [
            ['Name', $entry->name],
            ['Version', $entry->version],
            ['Author', $entry->author ?? '-'],
            ['Description', $entry->description ?? '-'],
            ['Registry', $entry->source()],
            ['Download', $entry->download],
            ['SHA-256', $entry->sha256],
            ['Signature', $entry->signature !== null ? 'signed, verified against a trusted key before install' : 'not signed'],
        ];

        if ($entry->panelVersion !== null) {
            $rows[] = ['Panel version', $entry->panelVersion];
        }

        if ($installed !== null) {
            $rows[] = ['Replaces', "$installed->name $installed->version"];
        }

        return $rows;
    }
}
