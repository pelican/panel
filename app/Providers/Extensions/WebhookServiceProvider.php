<?php

namespace App\Providers\Extensions;

use App\Extensions\Webhooks\Schemas\RegularSchema;
use App\Extensions\Webhooks\WebhookTypeService;
use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WebhookTypeService::class, function ($app) {
            $service = new WebhookTypeService();

            $service->register(new RegularSchema());

            return $service;
        });
    }
}
