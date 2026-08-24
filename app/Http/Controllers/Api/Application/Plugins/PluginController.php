<?php

namespace App\Http\Controllers\Api\Application\Plugins;

use App\Data\Api\Application\PluginData;
use App\Enums\PluginStatus;
use App\Exceptions\PanelException;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Plugins\ImportFilePluginRequest;
use App\Http\Requests\Api\Application\Plugins\ReadPluginRequest;
use App\Http\Requests\Api\Application\Plugins\UninstallPluginRequest;
use App\Http\Requests\Api\Application\Plugins\WritePluginRequest;
use App\Models\Plugin;
use App\Services\Helpers\PluginService;
use Exception;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

class PluginController extends ApplicationApiController
{
    /**
     * PluginController constructor.
     */
    public function __construct(private readonly PluginService $pluginService)
    {
        parent::__construct();
    }

    /**
     * List plugins
     *
     * Return all plugins on the Panel.
     *
     * @return array<array-key, mixed>
     */
    public function index(ReadPluginRequest $request): array
    {
        $plugins = QueryBuilder::for(Plugin::class)
            ->allowedFilters(['id', 'name', 'author', 'category'])
            ->allowedSorts(['id', 'name', 'author', 'category'])
            ->paginate($request->query('per_page') ?? 10);

        return $this->response->collection($plugins)
            ->transformWith(PluginData::class)
            ->toArray();
    }

    /**
     * View plugin
     *
     * Return a single plugin.
     *
     * @return array<array-key, mixed>
     */
    public function view(ReadPluginRequest $request, Plugin $plugin): array
    {
        return $this->response->item($plugin)
            ->transformWith(PluginData::class)
            ->toArray();
    }

    /**
     * Import plugin (file)
     *
     * Imports a new plugin file.
     *
     * @throws Exception
     */
    public function importFile(WritePluginRequest $request): Response
    {
        throw_unless($request->hasFile('plugin'), new PanelException("No 'plugin' file in request"));

        $this->pluginService->downloadPluginFromFile($request->file('plugin'));

        return new Response('', Response::HTTP_CREATED);
    }

    /**
     * Import plugin (url)
     *
     * Imports a new plugin from an url.
     *
     * @throws Exception
     */
    public function importUrl(ImportFilePluginRequest $request): Response
    {
        $this->pluginService->downloadPluginFromUrl($request->input('url'));

        return new Response('', Response::HTTP_CREATED);
    }

    /**
     * Install plugin
     *
     * Installs and enables a plugin.
     *
     * @return array<array-key, mixed>
     *
     * @throws Exception
     */
    public function install(WritePluginRequest $request, Plugin $plugin): array
    {
        throw_if($plugin->status !== PluginStatus::NotInstalled, new PanelException('Plugin is already installed'));

        $this->pluginService->installPlugin($plugin);

        return $this->response->item($plugin)
            ->transformWith(PluginData::class)
            ->toArray();
    }

    /**
     * Update plugin
     *
     * Downloads and installs an update for a plugin. Will throw if no update is available.
     *
     * @return array<array-key, mixed>
     *
     * @throws Exception
     */
    public function update(WritePluginRequest $request, Plugin $plugin): array
    {
        throw_unless($plugin->isUpdateAvailable(), new PanelException("Plugin doesn't need updating"));

        $this->pluginService->updatePlugin($plugin);

        return $this->response->item($plugin)
            ->transformWith(PluginData::class)
            ->toArray();
    }

    /**
     * Uninstall plugin
     *
     * Uninstalls a plugin. Optionally it will delete the plugin folder too.
     *
     * @return array<array-key, mixed>
     *
     * @throws Exception
     */
    public function uninstall(UninstallPluginRequest $request, Plugin $plugin): array
    {
        throw_if($plugin->status === PluginStatus::NotInstalled, new PanelException('Plugin is not installed'));

        $this->pluginService->uninstallPlugin($plugin, $request->boolean('delete'));

        return $this->response->item($plugin)
            ->transformWith(PluginData::class)
            ->toArray();
    }

    /**
     * Enable plugin
     *
     * Enables a plugin.
     *
     * @return array<array-key, mixed>
     *
     * @throws Exception
     */
    public function enable(WritePluginRequest $request, Plugin $plugin): array
    {
        throw_unless($plugin->canEnable(), new PanelException("Plugin can't be enabled"));

        $this->pluginService->enablePlugin($plugin);

        return $this->response->item($plugin)
            ->transformWith(PluginData::class)
            ->toArray();
    }

    /**
     * Disable plugin
     *
     * Disables a plugin.
     *
     * @return array<array-key, mixed>
     *
     * @throws Exception
     */
    public function disable(WritePluginRequest $request, Plugin $plugin): array
    {
        throw_unless($plugin->canDisable(), new PanelException("Plugin can't be disabled"));

        $this->pluginService->disablePlugin($plugin);

        return $this->response->item($plugin)
            ->transformWith(PluginData::class)
            ->toArray();
    }
}
