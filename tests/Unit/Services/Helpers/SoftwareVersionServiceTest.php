<?php

namespace App\Tests\Unit\Services\Helpers;

use App\Services\Helpers\SoftwareVersionService;
use App\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

class SoftwareVersionServiceTest extends TestCase
{
    private SoftwareVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SoftwareVersionService();
        cache()->flush();
    }

    #[DataProvider('isLatestPanelDataProvider')]
    public function test_is_latest_panel(string $current, string $latest, bool $expected): void
    {
        config()->set('app.version', $current);
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v' . $latest])]);

        $this->assertSame($expected, $this->service->isLatestPanel());
    }

    public static function isLatestPanelDataProvider(): array
    {
        return [
            'canary is always latest' => ['canary', '1.0.0', true],
            'equal versions' => ['1.0.0', '1.0.0', true],
            'newer than latest' => ['1.1.0', '1.0.0', true],
            'older than latest' => ['0.9.0', '1.0.0', false],
            'prerelease older than its stable' => ['1.0.0-rc1', '1.0.0', false],
            'stable newer than a prerelease' => ['1.0.0', '1.0.0-rc1', true],
        ];
    }

    public function test_latest_versions_are_null_when_fetch_fails(): void
    {
        Http::fake(['api.github.com/*' => Http::response(null, 500)]);

        $this->assertNull($this->service->latestPanelVersion());
        $this->assertNull($this->service->latestWingsVersion());
        $this->assertNull($this->service->latestPanelVersionChangelog());
    }

    public function test_is_latest_panel_when_fetch_fails(): void
    {
        config()->set('app.version', '1.0.0');
        Http::fake(['api.github.com/*' => Http::response(null, 500)]);

        $this->assertTrue($this->service->isLatestPanel());
    }

    public function test_latest_panel_version_returns_trimmed_tag(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v1.2.3'])]);

        $this->assertSame('1.2.3', $this->service->latestPanelVersion());
    }

    public function test_current_panel_version_prefers_stamped_version_over_git(): void
    {
        config()->set('app.version', '1.2.3');

        $this->assertSame('1.2.3', $this->service->currentPanelVersion());
        $this->assertSame('1.2.3', $this->service->currentComparableVersion());
    }

    public function test_current_comparable_version_is_null_on_canary(): void
    {
        config()->set('app.version', 'canary');

        $this->assertNull($this->service->currentComparableVersion());
    }
}
