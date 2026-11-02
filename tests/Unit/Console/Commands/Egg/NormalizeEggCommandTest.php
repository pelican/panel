<?php

namespace App\Tests\Unit\Console\Commands\Egg {
    use App\Tests\TestCase;
    use Carbon\CarbonInterface;
    use Illuminate\Support\Carbon;
    use Illuminate\Testing\PendingCommand;
    use Symfony\Component\Uid\Uuid;

    use function PHPUnit\Framework\assertFileDoesNotExist;
    use function PHPUnit\Framework\assertFileEquals;
    use function PHPUnit\Framework\assertFileExists;

    class NormalizeEggCommandTest extends TestCase
    {
        private ?string $tmpdir = null;

        private ?CarbonInterface $origTime = null;

        public function test_upgrade_ptdlv2_to_plcnv3(): void
        {
            $orig = $this->tmpdir . '/egg-ptdlv2.json';
            $this->copyExampleFile('egg-ptdlv2.json');
            $this->runCommand($orig)
                ->expectsOutputToContain(' -> exporting to ')
                ->run();

            assertFileEquals(
                dirname(__FILE__) . '/egg-plcnv3-example.yaml',
                substr($orig, 0, -4) . 'yaml',
            );
        }

        /**
         * @depends test_upgrade_ptdlv2_to_plcnv3
         */
        public function test_idempotency(): void
        {
            $orig = $this->tmpdir . '/egg-ptdlv2.json';
            $this->copyExampleFile('egg-ptdlv2.json');
            $this->runCommand($orig)
                ->expectsOutputToContain(' -> exporting to ')
                ->run();

            $newFile = substr($orig, 0, -4) . 'yaml';
            assertFileExists($newFile);

            copy($newFile, $this->tmpdir . '/copy.yaml');

            // turn off datetime mocking (i.e. get a new datetime)
            // so we can ensure a new datetime is only written to the file
            // if the content has changed
            Carbon::setTestNow(null);

            $this->runCommand($newFile)
                ->expectsOutputToContain(' -> no changes required')
                ->doesntExpectOutputToContain(' -> exporting to ')
                ->run();

            assertFileEquals(
                $newFile,
                $this->tmpdir . '/copy.yaml',
            );
        }

        public function test_delete_original(): void
        {
            $orig = $this->tmpdir . '/egg-ptdlv2.json';
            $this->copyExampleFile('egg-ptdlv2.json');

            $this->runCommand(' --delete-original ' . $orig)
                ->expectsOutputToContain(' -> exporting to ')
                ->expectsOutputToContain(' -> deleting input file as requested')
                ->run();

            $newFile = substr($orig, 0, -4) . 'yaml';

            assertFileExists($newFile);
            assertFileDoesNotExist($orig);
        }

        public function test_dont_delete_if_error(): void
        {
            $orig = $this->tmpdir . '/egg-ptdlv2.json';
            $this->copyExampleFile('egg-ptdlv2.json');

            $GLOBALS['SHOULD_MOCK_FILE_PUT'] = true;

            $this->artisan('p:egg:normalize --delete-original ' . $orig)
                ->expectsOutputToContain('Importing ')
                ->expectsOutputToContain(' -> failed to write output file')
                ->assertFailed()
                ->run();

            unset($GLOBALS['SHOULD_MOCK_FILE_PUT']);

            $newFile = substr($orig, 0, -4) . 'yaml';

            assertFileDoesNotExist($newFile);
            assertFileExists($orig);
        }

        private function runCommand(string $arguments): PendingCommand
        {
            return $this->artisan('p:egg:normalize ' . $arguments)
                ->expectsOutputToContain('Importing ')
                ->assertSuccessful();
        }

        private function copyExampleFile(string $filename): void
        {
            throw_unless(copy(
                dirname(__FILE__) . '/' . $filename,
                $this->tmpdir . '/' . $filename), new \Exception('unable to copy json file to tmpdir'));
        }

        protected function setUp(): void
        {
            parent::setUp();

            register_shutdown_function([$this, 'shutdownHandler']);

            $this->tmpdir = sys_get_temp_dir() . '/' . Uuid::v4()->toRfc4122();
            throw_unless(mkdir($this->tmpdir), new \Exception('unable to create tmp dir'));

            // save the datetime set by the test framework to restore it after out tests
            $this->origTime = Carbon::getTestNow();
            Carbon::setTestNow(Carbon::createFromDate(2026, 06, 01)->setTime(01, 02, 03));
        }

        protected function tearDown(): void
        {
            parent::tearDown();

            Carbon::setTestNow($this->origTime);

            if (!is_null($this->tmpdir)) {
                foreach (glob("{$this->tmpdir}/*") as $filename) {
                    unlink($filename);
                }
                rmdir($this->tmpdir);
            }
        }

        public function shutdownHandler(): void
        {
            // try and delete our tmp files to leave everything clean
            error_get_last() && $this->tearDown();
        }
    }
}

namespace App\Console\Commands\Egg {
    /** @phpstan-ignore missingType.parameter */
    function file_put_contents(string $filename, mixed $data, int $flags = 0, $context = null): int|false
    {
        if (($GLOBALS['SHOULD_MOCK_FILE_PUT'] ?? false) === true) {
            return false;
        }

        return \file_put_contents($filename, $data, $flags, $context);
    }
}
