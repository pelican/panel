<?php

namespace App\Tests\Unit\Models;

use App\Models\Plugin;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PluginApiVersionTest extends TestCase
{
    #[DataProvider('apiVersionDataProvider')]
    public function test_api_version_support(?int $apiVersion, int $expectedEffective, bool $expectedSupported): void
    {
        $plugin = new Plugin();
        $plugin->api_version = $apiVersion;

        $this->assertSame($expectedEffective, $plugin->effectiveApiVersion());
        $this->assertSame($expectedSupported, $plugin->isApiVersionSupported());
    }

    public static function apiVersionDataProvider(): array
    {
        return [
            'declared supported version' => [Plugin::SUPPORTED_API_VERSION, Plugin::SUPPORTED_API_VERSION, true],
            'missing defaults to 1' => [null, 1, true],
            'newer than supported' => [Plugin::SUPPORTED_API_VERSION + 1, Plugin::SUPPORTED_API_VERSION + 1, false],
        ];
    }
}
