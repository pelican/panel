<?php

namespace App\Providers;

use App\Checks\CacheCheck;
use App\Checks\DatabaseCheck;
use App\Checks\DebugModeCheck;
use App\Checks\EnvironmentCheck;
use App\Checks\NodeVersionsCheck;
use App\Checks\PanelVersionCheck;
use App\Checks\ScheduleCheck;
use App\Checks\UsedDiskSpaceCheck;
use App\Extensions\Dedoc\Scramble\FractalResponseTypeInfer;
use App\Extensions\Dedoc\Scramble\TransformerFactoryTypeInfer;
use App\Extensions\Dedoc\Scramble\TransformerModelBindingExtension;
use App\Http\Responses\LoginResponse;
use App\Models\Allocation;
use App\Models\ApiKey;
use App\Models\Backup;
use App\Models\Database;
use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\Node;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use App\Models\UserSSHKey;
use App\Services\Helpers\PluginService;
use App\Services\Helpers\SoftwareVersionService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passkeys\Passkeys;
use Laravel\Sanctum\Sanctum;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(
        Application $app,
        SoftwareVersionService $versionService,
        Repository $config,
    ): void {
        $appUrl = config('app.url') ?? '';

        URL::forceHttps(Str::startsWith($appUrl, 'https://'));

        URL::useOrigin($appUrl);
        URL::useAssetOrigin($appUrl);

        if ($app->runningInConsole() && empty(config('app.key'))) {
            $config->set('app.key', '');
        }

        Relation::enforceMorphMap([
            'allocation' => Allocation::class,
            'api_key' => ApiKey::class,
            'backup' => Backup::class,
            'database' => Database::class,
            'egg' => Egg::class,
            'egg_variable' => EggVariable::class,
            'schedule' => Schedule::class,
            'server' => Server::class,
            'ssh_key' => UserSSHKey::class,
            'task' => Task::class,
            'user' => User::class,
            'node' => Node::class,
        ]);

        Http::macro(
            'daemon',
            fn (Node $node, array $headers = []) => Http::acceptJson()
                ->asJson()
                ->withToken($node->daemon_token)
                ->withHeaders($headers)
                ->withOptions(['verify' => (bool) $app->environment('production')])
                ->timeout(config('panel.guzzle.timeout'))
                ->connectTimeout(config('panel.guzzle.connect_timeout'))
                ->baseUrl($node->getConnectionAddress())
        );

        Passkeys::useUserModel(User::class);

        Sanctum::usePersonalAccessTokenModel(ApiKey::class);

        Gate::define('viewApiDocs', fn () => true);

        $bearerTokens = fn (OpenApi $openApi) => $openApi->secure(SecurityScheme::http('bearer'));
        Scramble::registerApi('application', ['api_path' => 'api/application', 'info' => ['version' => '1.0']])->afterOpenApiGenerated($bearerTokens);
        Scramble::registerApi('client', ['api_path' => 'api/client', 'info' => ['version' => '1.0']])->afterOpenApiGenerated($bearerTokens);

        // Don't run any health checks during tests
        if (!$app->runningUnitTests()) {
            Health::checks([
                DebugModeCheck::new()->if($app->isProduction()),
                EnvironmentCheck::new(),
                CacheCheck::new(),
                DatabaseCheck::new(),
                QueueCheck::new(),
                ScheduleCheck::new(),
                UsedDiskSpaceCheck::new(),
                PanelVersionCheck::new(),
                NodeVersionsCheck::new(),
            ]);
        }

        Gate::before(fn (User $user, $ability) => $user->isRootAdmin() ? true : null);

        AboutCommand::add('Pelican', [
            'Panel Version' => $versionService->currentPanelVersion(),
            'Latest Version' => $versionService->latestPanelVersion(),
            'Up-to-Date' => $versionService->isLatestPanel() ? '<fg=green;options=bold>Yes</>' : '<fg=red;options=bold>No</>',
        ]);

        AboutCommand::add('Environment', 'Installation Directory', base_path());
    }

    /**
     * Register application service providers.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);

        Scramble::ignoreDefaultRoutes();

        // Registered here rather than in boot() because Scramble's own provider boots first, and
        // it reads some kinds of extension right then. Registering during register() works for
        // every kind, so this keeps holding once an operation extension is added.
        Scramble::registerExtensions([
            TransformerModelBindingExtension::class,
            TransformerFactoryTypeInfer::class,
            FractalResponseTypeInfer::class,
        ]);

        /** @var PluginService $pluginService */
        $pluginService = app(PluginService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $pluginService->loadPlugins();
    }
}
