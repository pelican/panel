<?php

namespace App\Tests\Integration\Api\Application;

use App\Models\Plugin;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use ZipArchive;

/**
 * Plugin contract v3, R2.5: "require signatures" covers archives imported through the API too, and a signature
 * sent with an import is checked.
 */
class PluginImportSignatureTest extends ApplicationApiIntegrationTestCase
{
    private const URL = 'https://downloads.example.test/api-signed.zip';

    private string $archive;

    private string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();

        Process::fake();

        $pair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($pair);
        config()->set('panel.plugin.trusted_keys', base64_encode(sodium_crypto_sign_publickey($pair)));
        config()->set('panel.plugin.require_signatures', true);

        $path = tempnam(sys_get_temp_dir(), 'api') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('api-signed/plugin.json', json_encode(['id' => 'api-signed', 'name' => 'API Signed', 'version' => '1.0.0']));
        $zip->close();
        $this->archive = (string) file_get_contents($path);
        unlink($path);

        Http::fake([self::URL => Http::response($this->archive)]);

        $this->createNewDefaultApiKey($this->getApiUser(), [Plugin::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(plugin_path('api-signed'));
        Plugin::refreshRows();

        parent::tearDown();
    }

    public function test_url_import_needs_a_valid_signature_when_signatures_are_required(): void
    {
        $this->postJson('/api/application/plugins/import/url', ['url' => self::URL])->assertServerError();
        $this->assertDirectoryDoesNotExist(plugin_path('api-signed'));

        $this->postJson('/api/application/plugins/import/url', ['url' => self::URL, 'signature' => $this->sign($this->archive)])
            ->assertStatus(Response::HTTP_CREATED);
        $this->assertFileExists(plugin_path('api-signed', 'plugin.json'));
    }

    public function test_file_import_needs_a_valid_signature_when_signatures_are_required(): void
    {
        $this->post('/api/application/plugins/import/file', ['plugin' => $this->upload()])->assertServerError();
        $this->assertDirectoryDoesNotExist(plugin_path('api-signed'));

        $this->post('/api/application/plugins/import/file', ['plugin' => $this->upload(), 'signature' => $this->sign('something else')])->assertServerError();
        $this->assertDirectoryDoesNotExist(plugin_path('api-signed'));

        $this->post('/api/application/plugins/import/file', ['plugin' => $this->upload(), 'signature' => $this->sign($this->archive)])
            ->assertStatus(Response::HTTP_CREATED);
        $this->assertFileExists(plugin_path('api-signed', 'plugin.json'));
    }

    private function sign(string $bytes): string
    {
        return base64_encode(sodium_crypto_sign_detached(hash('sha256', $bytes, true), $this->secretKey));
    }

    private function upload(): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'api');
        file_put_contents($path, $this->archive);

        return new UploadedFile($path, 'api-signed.zip', 'application/zip', null, true);
    }
}
