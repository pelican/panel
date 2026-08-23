<?php

namespace App\Tests\Unit\Helpers;

use App\Helpers\PasskeyOrigin;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PasskeyOriginTest extends TestCase
{
    /**
     * Test that a URL is reduced to the origin a browser reports during a ceremony.
     */
    #[DataProvider('normalizeDataProvider')]
    public function test_normalize(?string $url, ?string $expected): void
    {
        $this->assertSame($expected, PasskeyOrigin::normalize($url));
    }

    /**
     * Provide URLs to normalize alongside the origin they reduce to.
     */
    public static function normalizeDataProvider(): array
    {
        return [
            'default https port is dropped' => ['https://panel.example.com:443', 'https://panel.example.com'],
            'default http port is dropped' => ['http://localhost:80', 'http://localhost'],
            'non default port is kept' => ['https://panel.example.com:8443', 'https://panel.example.com:8443'],
            'bare host becomes https' => ['panel.example.com', 'https://panel.example.com'],
            'bare host with a port becomes https' => ['panel.example.com:8443', 'https://panel.example.com:8443'],
            'scheme and host are lowercased' => ['HTTPS://Panel.Example.COM', 'https://panel.example.com'],
            'path is stripped' => ['https://panel.example.com/admin/passkeys', 'https://panel.example.com'],
            'query and fragment are stripped' => ['https://panel.example.com/?a=b#c', 'https://panel.example.com'],
            'trailing slash is stripped' => ['https://panel.example.com/', 'https://panel.example.com'],
            'surrounding whitespace is ignored' => ['  https://panel.example.com  ', 'https://panel.example.com'],
            'ipv4 loopback keeps its port' => ['http://127.0.0.1:8000', 'http://127.0.0.1:8000'],
            'ipv6 loopback keeps its brackets' => ['http://[::1]:8000', 'http://[::1]:8000'],
            'null has no origin' => [null, null],
            'empty string has no origin' => ['', null],
            'whitespace only has no origin' => ['   ', null],
            'a value without a host has no origin' => ['https://', null],
        ];
    }

    /**
     * Test that a list of URLs collapses to the distinct origins it represents.
     */
    public function test_all_normalizes_deduplicates_and_reindexes(): void
    {
        $origins = PasskeyOrigin::all([
            'https://panel.example.com',
            'https://panel.example.com:443',
            'panel.example.com',
            'https://other.example.com:8443',
            '',
            null,
        ]);

        $this->assertSame([
            'https://panel.example.com',
            'https://other.example.com:8443',
        ], $origins);
    }
}
