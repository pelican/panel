<?php

use App\Console\Kernel;
use App\Exceptions\Handler;
use App\Helpers\ProcessEnv;
use App\Http\Middleware\Activity\TrackAPIKey;
use App\Http\Middleware\Api\Application\AuthenticateApplicationUser;
use App\Http\Middleware\Api\AuthenticateIPAccess;
use App\Http\Middleware\Api\Client\RequireClientApiKey;
use App\Http\Middleware\Api\Daemon\DaemonAuthenticate;
use App\Http\Middleware\Api\IsValidJson;
use App\Http\Middleware\EnsureStatefulRequests;
use App\Http\Middleware\LanguageMiddleware;
use App\Http\Middleware\MaintenanceMiddleware;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as IlluminatePreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('filament.app.auth.login'));

        $middleware->web([
            LanguageMiddleware::class,
            SetSecurityHeaders::class,
        ]);

        $middleware->api([
            EnsureStatefulRequests::class,
            'auth:sanctum',
            IsValidJson::class,
            TrackAPIKey::class,
            AuthenticateIPAccess::class,
        ]);

        $middleware->group('application-api', [
            SubstituteBindings::class,
            AuthenticateApplicationUser::class,
        ]);

        $middleware->group('client-api', [
            SubstituteBindings::class,
            RequireClientApiKey::class,
        ]);

        $middleware->group('daemon', [
            SubstituteBindings::class,
            DaemonAuthenticate::class,
        ]);

        $middleware->replaceInGroup('web', IlluminatePreventRequestForgery::class, PreventRequestForgery::class);

        // The panel updater hands the admin a maintenance bypass cookie, which is checked before cookies are decrypted.
        $middleware->encryptCookies(except: ['laravel_maintenance']);

        $middleware->alias([
            'bindings' => SubstituteBindings::class,
            'guest' => RedirectIfAuthenticated::class,
            'node.maintenance' => MaintenanceMiddleware::class,
        ]);

        $middleware->priority([
            SubstituteBindings::class,
        ]);
    })
    ->withSingletons([
        Illuminate\Contracts\Console\Kernel::class => Kernel::class,
        ExceptionHandler::class => Handler::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {})
    ->create();

// Snapshot the real process environment before .env is loaded on top of it.
$app->beforeBootstrapping(LoadEnvironmentVariables::class, fn () => ProcessEnv::capture());

return $app;
