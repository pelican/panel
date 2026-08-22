<?php

namespace App\Facades;

use App\Extensions\Webhooks\Schemas\WebhookSchemaInterface;
use App\Extensions\Webhooks\WebhookTypeService;
use BackedEnum;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(WebhookSchemaInterface $schema)
 * @method static array<string, WebhookSchemaInterface> getAll()
 * @method static WebhookSchemaInterface|null get(?string $id)
 * @method static bool has(?string $id)
 * @method static array<string, string> getOptions()
 * @method static array<string, string|BackedEnum|null> getIcons()
 * @method static array<string, string|null> getColors()
 * @method static string detect(?string $endpoint)
 * @method static string detectFor(?string $endpoint, ?string $currentType)
 *
 * @see WebhookTypeService
 */
class WebhookTypes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WebhookTypeService::class;
    }
}
