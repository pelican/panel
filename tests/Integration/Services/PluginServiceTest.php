<?php

namespace App\Tests\Integration\Services;

use App\Services\Helpers\PluginService;
use App\Tests\Integration\IntegrationTestCase;
use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use ZipArchive;

class PluginServiceTest extends IntegrationTestCase
{
    private PluginService $service;

    /** @var string[] */
    private array $importedPlugins = [];

    /** @var TemporaryDirectory[] */
    private array $uploadDirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(PluginService::class);
    }

    protected function tearDown(): void
    {
        // Clean up anything the service touched in the real plugins directory: installed
        // plugins, their rollback backups, and any leftover extraction working directories.
        foreach ($this->importedPlugins as $id) {
            File::deleteDirectory(plugin_path($id));
            File::deleteDirectory(plugin_path('.' . $id . '.bak'));
        }

        foreach (File::glob(plugin_path('.import-*')) as $leftover) {
            File::deleteDirectory($leftover);
        }

        // TemporaryDirectory::make() does not clean up on its own; remove the upload fixtures.
        foreach ($this->uploadDirs as $dir) {
            $dir->delete();
        }

        parent::tearDown();
    }

    public function test_import_uses_plugin_json_id_not_the_upload_filename(): void
    {
        // Zip contains a top-level folder "test-import-plugin", but the upload is renamed as
        // a browser would on a duplicate download.
        $file = $this->makeUpload('test-import-plugin(1).zip', [
            'test-import-plugin/plugin.json' => $this->manifest('test-import-plugin'),
        ]);

        $this->importedPlugins[] = 'test-import-plugin';

        $id = $this->service->downloadPluginFromFile($file);

        $this->assertSame('test-import-plugin', $id);
        $this->assertFileExists(plugin_path('test-import-plugin', 'plugin.json'));
        // The (1) suffixed folder must never be created.
        $this->assertDirectoryDoesNotExist(plugin_path('test-import-plugin(1)'));
    }

    public function test_import_handles_flat_zip_without_a_top_level_folder(): void
    {
        $file = $this->makeUpload('renamed(2).zip', [
            'plugin.json' => $this->manifest('test-flat-plugin'),
        ]);

        $this->importedPlugins[] = 'test-flat-plugin';

        $id = $this->service->downloadPluginFromFile($file);

        $this->assertSame('test-flat-plugin', $id);
        $this->assertFileExists(plugin_path('test-flat-plugin', 'plugin.json'));
    }

    public function test_import_lowercases_the_manifest_id(): void
    {
        $file = $this->makeUpload('Test-Upper.zip', [
            'Test-Upper/plugin.json' => $this->manifest('Test-Upper'),
        ]);

        $this->importedPlugins[] = 'test-upper';

        $id = $this->service->downloadPluginFromFile($file);

        $this->assertSame('test-upper', $id);
        $this->assertFileExists(plugin_path('test-upper', 'plugin.json'));
    }

    public function test_import_cleans_up_its_extraction_directory(): void
    {
        $this->importedPlugins[] = 'test-cleanup-plugin';

        $this->service->downloadPluginFromFile($this->makeUpload('test-cleanup-plugin.zip', [
            'test-cleanup-plugin/plugin.json' => $this->manifest('test-cleanup-plugin'),
        ]));

        // The temporary ".import-*" working directory must not linger under plugins/.
        $this->assertSame([], File::glob(plugin_path('.import-*')));
    }

    public function test_import_without_a_manifest_fails_loudly(): void
    {
        $file = $this->makeUpload('not-a-plugin.zip', [
            'readme.txt' => 'nothing to see here',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(trans('admin/plugin.notifications.import_no_manifest'));

        $this->service->downloadPluginFromFile($file);
    }

    public function test_import_with_a_non_string_id_fails_loudly(): void
    {
        $file = $this->makeUpload('bad-id.zip', [
            'plugin.json' => json_encode(['id' => []]),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(trans('admin/plugin.notifications.import_no_manifest'));

        $this->service->downloadPluginFromFile($file);
    }

    public function test_import_with_a_missing_id_fails_loudly(): void
    {
        $file = $this->makeUpload('no-id.zip', [
            'plugin.json' => json_encode(['name' => 'No Id']),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(trans('admin/plugin.notifications.import_no_manifest'));

        $this->service->downloadPluginFromFile($file);
    }

    public function test_import_with_an_empty_id_fails_loudly(): void
    {
        $file = $this->makeUpload('empty-id.zip', [
            'plugin.json' => json_encode(['id' => '']),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(trans('admin/plugin.notifications.import_no_manifest'));

        $this->service->downloadPluginFromFile($file);
    }

    public function test_import_ignores_a_plugin_json_nested_too_deep(): void
    {
        // locatePluginManifest only looks at the root and one level deep; anything deeper is
        // not treated as a plugin.
        $file = $this->makeUpload('deep.zip', [
            'a/b/plugin.json' => $this->manifest('too-deep'),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(trans('admin/plugin.notifications.import_no_manifest'));

        $this->service->downloadPluginFromFile($file);
    }

    public function test_import_rejects_a_zip_with_a_path_traversal_entry(): void
    {
        // A malicious archive entry that would write outside the extraction directory must be
        // rejected before extraction, even if it also carries a valid plugin.json.
        $file = $this->makeUpload('evil.zip', [
            '../evil.txt' => 'pwned',
            'evil/plugin.json' => $this->manifest('evil'),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Zip file contains invalid path traversal sequences.');

        $this->service->downloadPluginFromFile($file);
    }

    public function test_import_rejects_a_zip_larger_than_the_configured_max(): void
    {
        config()->set('panel.plugin.max_import_size', 10);

        $file = $this->makeUpload('too-big.zip', [
            'too-big/plugin.json' => $this->manifest('too-big'),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Zip file too large');

        $this->service->downloadPluginFromFile($file);
    }

    public function test_import_rejects_a_zip_that_expands_beyond_the_configured_max(): void
    {
        // Clean up in case a regression lets the archive through and it actually installs.
        $this->importedPlugins[] = 'zip-bomb';

        config()->set('panel.plugin.max_import_size', 4096);

        $file = $this->makeUpload('zip-bomb.zip', [
            'zip-bomb/plugin.json' => $this->manifest('zip-bomb'),
            'zip-bomb/payload.txt' => str_repeat('a', 100_000),
        ]);

        // The archive compresses down small enough to clear the compressed-size check, so only
        // the uncompressed total catches it.
        $this->assertLessThan(4096, $file->getSize());

        $thrown = null;

        try {
            $this->service->downloadPluginFromFile($file);
        } catch (Exception $exception) {
            $thrown = $exception;
        }

        $this->assertNotNull($thrown, 'Expected the oversized archive to be rejected.');
        $this->assertStringContainsString('Zip file contents too large', $thrown->getMessage());

        // Nothing should have been written: no install, and no leftover extraction directory.
        $this->assertDirectoryDoesNotExist(plugin_path('zip-bomb'));
        $this->assertSame([], File::glob(plugin_path('.import-*')));
    }

    public function test_import_from_url_downloads_and_installs_the_plugin(): void
    {
        $this->importedPlugins[] = 'test-url-plugin';

        $bytes = file_get_contents($this->makeUpload('whatever.zip', [
            'test-url-plugin/plugin.json' => $this->manifest('test-url-plugin'),
        ])->getPathname());

        Http::fake(['*' => Http::response($bytes)]);

        $id = $this->service->downloadPluginFromUrl('https://example.test/download/test-url-plugin.zip');

        $this->assertSame('test-url-plugin', $id);
        $this->assertFileExists(plugin_path('test-url-plugin', 'plugin.json'));
    }

    /**
     * @param  string  $id  an id that is a non-empty string but not a safe directory slug
     */
    #[DataProvider('unsafeIds')]
    public function test_import_rejects_an_unsafe_plugin_id(string $id): void
    {
        // A clean entry name (so it passes the archive path-traversal check) whose manifest
        // still carries a dangerous id must be rejected before anything is moved into place.
        $file = $this->makeUpload('sneaky.zip', [
            'plugin.json' => json_encode(['id' => $id]),
        ]);

        try {
            $this->service->downloadPluginFromFile($file);
            $this->fail('Expected the import to be rejected for id: ' . $id);
        } catch (Exception $e) {
            $this->assertSame(trans('admin/plugin.notifications.import_invalid_id'), $e->getMessage());
        }

        // Nothing should have escaped the plugins directory.
        $this->assertDirectoryDoesNotExist(base_path('evil'));
        $this->assertDirectoryDoesNotExist(base_path('plugins/../evil'));
    }

    /** @return array<string, array{string}> */
    public static function unsafeIds(): array
    {
        return [
            'parent traversal' => ['../../evil'],
            'single parent' => ['../evil'],
            'contains slash' => ['foo/bar'],
            'leading dot' => ['.hidden'],
            'just dots' => ['..'],
            'contains space' => ['foo bar'],
        ];
    }

    public function test_import_over_an_existing_plugin_replaces_it(): void
    {
        $this->importedPlugins[] = 'test-replace-plugin';

        $this->service->downloadPluginFromFile($this->makeUpload('test-replace-plugin.zip', [
            'test-replace-plugin/plugin.json' => $this->manifest('test-replace-plugin', '1.0.0'),
        ]));

        $this->assertSame('1.0.0', $this->installedVersion('test-replace-plugin'));

        // Re-importing the same id is allowed and replaces the existing install.
        $id = $this->service->downloadPluginFromFile($this->makeUpload('test-replace-plugin.zip', [
            'test-replace-plugin/plugin.json' => $this->manifest('test-replace-plugin', '2.0.0'),
        ]));

        // The new copy is in place and no rollback backup is left behind.
        $this->assertSame('test-replace-plugin', $id);
        $this->assertSame('2.0.0', $this->installedVersion('test-replace-plugin'));
        $this->assertDirectoryDoesNotExist(plugin_path('.test-replace-plugin.bak'));
    }

    public function test_import_succeeds_when_the_id_matches_the_expected_id(): void
    {
        $this->importedPlugins[] = 'test-expected-plugin';

        $id = $this->service->downloadPluginFromFile($this->makeUpload('test-expected-plugin.zip', [
            'test-expected-plugin/plugin.json' => $this->manifest('test-expected-plugin'),
        ]), 'test-expected-plugin');

        $this->assertSame('test-expected-plugin', $id);
        $this->assertFileExists(plugin_path('test-expected-plugin', 'plugin.json'));
    }

    public function test_import_rejects_an_archive_for_a_different_plugin_than_expected(): void
    {
        // Stand in for an update whose archive actually claims to be a *different* plugin than
        // the one being updated. It must be rejected before anything is written, so the update
        // can neither reinstall the wrong plugin nor overwrite an unrelated one.
        $this->importedPlugins[] = 'test-target-plugin';
        $this->importedPlugins[] = 'test-other-plugin';

        $this->service->downloadPluginFromFile($this->makeUpload('test-target-plugin.zip', [
            'test-target-plugin/plugin.json' => $this->manifest('test-target-plugin', '1.0.0'),
        ]));

        $file = $this->makeUpload('update.zip', [
            'test-other-plugin/plugin.json' => $this->manifest('test-other-plugin', '9.9.9'),
        ]);

        try {
            $this->service->downloadPluginFromFile($file, 'test-target-plugin');
            $this->fail('Expected a mismatched update to be rejected.');
        } catch (Exception $e) {
            $this->assertSame(
                trans('admin/plugin.notifications.import_id_mismatch', ['expected' => 'test-target-plugin', 'actual' => 'test-other-plugin']),
                $e->getMessage()
            );
        }

        // The intended plugin is untouched and the archive's plugin was never written.
        $this->assertSame('1.0.0', $this->installedVersion('test-target-plugin'));
        $this->assertDirectoryDoesNotExist(plugin_path('test-other-plugin'));
    }

    public function test_import_keeps_the_existing_plugin_when_the_move_fails(): void
    {
        $this->importedPlugins[] = 'test-clean-plugin';

        $this->service->downloadPluginFromFile($this->makeUpload('test-clean-plugin.zip', [
            'test-clean-plugin/plugin.json' => $this->manifest('test-clean-plugin', '1.0.0'),
        ]));

        // Force only the move into plugins/<id> to fail, standing in for a filesystem-level
        // failure mid-replace. The set-aside and restore moves (to/from the .bak dir) run for
        // real so we can prove the original install is restored intact.
        $real = new Filesystem();
        File::partialMock()
            ->shouldReceive('moveDirectory')
            ->andReturnUsing(function ($from, $to) use ($real) {
                if (str_ends_with($to, '.bak') || str_ends_with($from, '.bak')) {
                    return $real->moveDirectory($from, $to);
                }

                return false;
            });

        $file = $this->makeUpload('test-clean-plugin.zip', [
            'test-clean-plugin/plugin.json' => $this->manifest('test-clean-plugin', '2.0.0'),
        ]);

        try {
            $this->service->downloadPluginFromFile($file);
            $this->fail('Expected the import to fail.');
        } catch (Exception $e) {
            $this->assertSame('Could not move plugin into place.', $e->getMessage());
        }

        // The original 1.0.0 install must survive — not the failed 2.0.0 replacement — and the
        // backup directory must be gone.
        $this->assertSame('1.0.0', $this->installedVersion('test-clean-plugin'));
        $this->assertDirectoryDoesNotExist(plugin_path('.test-clean-plugin.bak'));
    }

    private function installedVersion(string $id): string
    {
        return File::json(plugin_path($id, 'plugin.json'))['version'];
    }

    private function manifest(string $id, string $version = '1.0.0'): string
    {
        return json_encode(['id' => $id, 'version' => $version]);
    }

    /**
     * Build a zip on disk from the given [path => contents] map and wrap it in an UploadedFile.
     *
     * @param  array<string, string>  $entries
     */
    private function makeUpload(string $clientName, array $entries): UploadedFile
    {
        $tmpDir = TemporaryDirectory::make();
        $this->uploadDirs[] = $tmpDir;
        $zipPath = $tmpDir->path('upload.zip');

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        foreach ($entries as $path => $contents) {
            $zip->addFromString($path, $contents);
        }
        $zip->close();

        // test mode (last arg) bypasses the is_uploaded_file() check.
        return new UploadedFile($zipPath, $clientName, 'application/zip', null, true);
    }
}
