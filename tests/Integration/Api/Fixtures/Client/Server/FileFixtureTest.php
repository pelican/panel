<?php

namespace App\Tests\Integration\Api\Fixtures\Client\Server;

use App\Repositories\Daemon\DaemonFileRepository;
use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class FileFixtureTest extends FixtureTestCase
{
    private const BASE = '/api/client/servers/00000000-0000-4000-8000-000000000400';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureOwner();

        $mock = \Mockery::mock(DaemonFileRepository::class);
        $this->app->instance(DaemonFileRepository::class, $mock);

        $mock->allows('setServer')->andReturnSelf();
        $mock->allows('getDirectory')->with('/')->andReturns([
            [
                'name' => 'server.jar',
                'mode' => '-rw-r--r--',
                'mode_bits' => '644',
                'size' => 1024,
                'file' => true,
                'symlink' => false,
                'mime' => 'application/jar',
                'created' => '2026-01-01T00:00:00Z',
                'modified' => '2026-01-01T00:00:00Z',
            ],
            [
                'name' => 'plugins',
                'mode' => 'drwxr-xr-x',
                'mode_bits' => '755',
                'size' => 4096,
                'file' => false,
                'symlink' => false,
                'mime' => 'inode/directory',
                'created' => '2026-01-01T00:00:00Z',
                'modified' => '2026-01-01T00:00:00Z',
            ],
        ]);
    }

    public function test_directory_listing(): void
    {
        $this->assertFixtureSnapshot($this->getJson(self::BASE . '/files/list?directory=%2F'));
    }
}
