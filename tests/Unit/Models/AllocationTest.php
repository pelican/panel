<?php

namespace App\Tests\Unit\Models;

use App\Models\Allocation;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AllocationTest extends TestCase
{
    #[DataProvider('displayAddressDataProvider')]
    public function test_display_address_respects_port_visibility(string $ip, ?string $alias, bool $showPort, string $expected): void
    {
        $allocation = new Allocation([
            'ip' => $ip,
            'ip_alias' => $alias,
            'port' => 25565,
            'show_port' => $showPort,
        ]);

        $this->assertSame($expected, $allocation->display_address);
    }

    public function test_port_is_shown_by_default(): void
    {
        $allocation = new Allocation([
            'ip' => '192.0.2.1',
            'port' => 25565,
        ]);

        $this->assertTrue($allocation->show_port);
        $this->assertSame('192.0.2.1:25565', $allocation->display_address);
    }

    public function test_hidden_port_falls_back_to_ip_when_alias_is_missing(): void
    {
        $allocation = new Allocation([
            'ip' => '192.0.2.1',
            'ip_alias' => null,
            'port' => 25565,
            'show_port' => false,
        ]);

        $this->assertSame('192.0.2.1', $allocation->display_address);
    }

    public static function displayAddressDataProvider(): array
    {
        return [
            'IPv4 with port' => ['192.0.2.1', null, true, '192.0.2.1:25565'],
            'IPv4 without port' => ['192.0.2.1', null, false, '192.0.2.1'],
            'IPv6 with port' => ['2001:db8::1', null, true, '[2001:db8::1]:25565'],
            'IPv6 without port' => ['2001:db8::1', null, false, '2001:db8::1'],
            'alias with port' => ['192.0.2.1', 'play.example.com', true, 'play.example.com:25565'],
            'alias without port' => ['192.0.2.1', 'play.example.com', false, 'play.example.com'],
        ];
    }
}
