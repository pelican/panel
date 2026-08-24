<?php

use App\Providers\ActivityLogServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\Extensions\AvatarServiceProvider;
use App\Providers\Extensions\BackupAdapterServiceProvider;
use App\Providers\Extensions\CaptchaServiceProvider;
use App\Providers\Extensions\FeatureServiceProvider;
use App\Providers\Extensions\OAuthServiceProvider;
use App\Providers\Extensions\TaskServiceProvider;
use App\Providers\Extensions\WebhookServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\Filament\FilamentServiceProvider;
use App\Providers\Filament\ServerPanelProvider;
use App\Providers\RouteServiceProvider;
use SocialiteProviders\Manager\ServiceProvider;

return [
    ActivityLogServiceProvider::class,
    AppServiceProvider::class,
    EventServiceProvider::class,
    AvatarServiceProvider::class,
    BackupAdapterServiceProvider::class,
    CaptchaServiceProvider::class,
    FeatureServiceProvider::class,
    OAuthServiceProvider::class,
    TaskServiceProvider::class,
    WebhookServiceProvider::class,
    FilamentServiceProvider::class,
    AdminPanelProvider::class,
    AppPanelProvider::class,
    ServerPanelProvider::class,
    RouteServiceProvider::class,
    ServiceProvider::class,
];
