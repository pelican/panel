<?php

namespace App\Tests\Unit\Models;

use App\Models\Plugin;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PluginCompatibilityTest extends TestCase
{
    #[DataProvider('compatibilityDataProvider')]
    public function test_is_compatible(?string $constraint, string $panelVersion, bool $expected): void
    {
        config()->set('app.version', $panelVersion);

        $plugin = new Plugin();
        $plugin->panel_version = $constraint;

        $this->assertSame($expected, $plugin->isCompatible());
    }

    public static function compatibilityDataProvider(): array
    {
        return [
            'no constraint' => [null, '1.0.0', true],
            'canary bypasses all constraints' => ['^1.0', 'canary', true],
            'strict match' => ['1.2.3', '1.2.3', true],
            'strict mismatch' => ['1.2.3', '1.2.4', false],
            'caret exact minimum' => ['^1.0', '1.0.0', true],
            'caret newer minor' => ['^1.0', '1.5.9', true],
            'caret below minimum' => ['^1.0', '0.9.0', false],
            'caret excludes next major' => ['^1.0', '2.0.0', false],
            'caret zero major patch' => ['^0.3', '0.3.1', true],
            'caret zero major excludes next minor' => ['^0.3', '0.4.0', false],
            'caret prerelease below minimum' => ['^1.0', '1.0.0-rc1', false],
            'caret full constraint newer patch' => ['^1.2.3', '1.2.4', true],
            'caret full constraint below patch' => ['^1.2.3', '1.2.2', false],
        ];
    }
}
