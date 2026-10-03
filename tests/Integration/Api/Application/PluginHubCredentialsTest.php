<?php

namespace App\Tests\Integration\Api\Application;

use App\Models\Plugin;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class PluginHubCredentialsTest extends ApplicationApiIntegrationTestCase
{
    private TemporaryDirectory $envDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Never touch the real .env: point the writer at a throwaway one.
        $this->envDir = TemporaryDirectory::make();
        File::put($this->envDir->path('.env'), "APP_NAME=Pelican\n");
        $this->app->useEnvironmentPath($this->envDir->path());
    }

    protected function tearDown(): void
    {
        $this->envDir->delete();

        parent::tearDown();
    }

    public function test_stores_the_hub_url_and_key(): void
    {
        $this->createNewDefaultApiKey($this->getApiUser(), [Plugin::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE]);

        $this->putJson('/api/application/plugins/hub', [
            'hub_url' => 'https://hub.pelican.dev/',
            'api_key' => 'pnl_abc123XYZ',
        ])->assertStatus(Response::HTTP_NO_CONTENT);

        $env = File::get($this->envDir->path('.env'));

        $this->assertStringContainsString('PANEL_PLUGIN_HUB_URL="https://hub.pelican.dev"', $env);
        $this->assertStringContainsString('PANEL_PLUGIN_HUB_API_KEY="pnl_abc123XYZ"', $env);
        $this->assertStringContainsString('APP_NAME=Pelican', $env);
    }

    public function test_clears_cached_update_checks_so_betas_show_up_immediately(): void
    {
        File::ensureDirectoryExists(plugin_path('test-hub-cache'));
        File::put(plugin_path('test-hub-cache', 'plugin.json'), json_encode(['id' => 'test-hub-cache', 'name' => 'Test', 'version' => '1.0.0']));
        Plugin::refreshRows();
        cache()->put('plugins.test-hub-cache.update', ['*' => ['version' => '1.0.0', 'download_url' => 'https://hub.pelican.dev/x.zip']], now()->addMinutes(10));

        try {
            $this->createNewDefaultApiKey($this->getApiUser(), [Plugin::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE]);

            $this->putJson('/api/application/plugins/hub', [
                'hub_url' => 'https://hub.pelican.dev',
                'api_key' => 'pnl_abc123',
            ])->assertStatus(Response::HTTP_NO_CONTENT);

            $this->assertFalse(cache()->has('plugins.test-hub-cache.update'));
        } finally {
            File::deleteDirectory(plugin_path('test-hub-cache'));
            Plugin::refreshRows();
        }
    }

    public function test_requires_plugin_write_permission(): void
    {
        $this->createNewDefaultApiKey($this->getApiUser(), [Plugin::RESOURCE_NAME => AdminAcl::READ]);

        $this->putJson('/api/application/plugins/hub', [
            'hub_url' => 'https://hub.pelican.dev',
            'api_key' => 'pnl_abc123',
        ])->assertForbidden();

        $this->assertStringNotContainsString('PANEL_PLUGIN_HUB', File::get($this->envDir->path('.env')));
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_rejects_unsafe_values(array $payload): void
    {
        $this->createNewDefaultApiKey($this->getApiUser(), [Plugin::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE]);

        $this->putJson('/api/application/plugins/hub', $payload)->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertStringNotContainsString('PANEL_PLUGIN_HUB', File::get($this->envDir->path('.env')));
    }

    /** @return array<string, array{array<string, string>}> */
    public static function invalidPayloads(): array
    {
        return [
            'plain http hub' => [['hub_url' => 'http://hub.pelican.dev', 'api_key' => 'pnl_abc']],
            'not a pnl key' => [['hub_url' => 'https://hub.pelican.dev', 'api_key' => 'papp_abc']],
            'env injection in key' => [['hub_url' => 'https://hub.pelican.dev', 'api_key' => "pnl_abc\nAPP_KEY=evil"]],
            'env injection in url' => [['hub_url' => "https://hub.pelican.dev\nAPP_KEY=evil", 'api_key' => 'pnl_abc']],
            'missing key' => [['hub_url' => 'https://hub.pelican.dev']],
        ];
    }
}
