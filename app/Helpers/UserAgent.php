<?php

namespace App\Helpers;

/**
 * Small user agent parser, just enough to label a signed-in device ("Firefox on Windows").
 */
final class UserAgent
{
    public const TYPE_DESKTOP = 'desktop';

    public const TYPE_MOBILE = 'mobile';

    public const TYPE_TABLET = 'tablet';

    public const TYPE_UNKNOWN = 'unknown';

    /** Order matters: Edge/Opera/Samsung contain "Chrome", Chrome contains "Safari". */
    private const BROWSERS = [
        'Edge' => '/\b(?:Edg|EdgA|EdgiOS|Edge)\//',
        'Opera' => '/\b(?:OPR|Opera|OPiOS)\//',
        'Samsung Internet' => '/\bSamsungBrowser\//',
        'Vivaldi' => '/\bVivaldi\//',
        'Firefox' => '/\b(?:Firefox|FxiOS)\//',
        'Chrome' => '/\b(?:Chrome|CriOS|Chromium)\//',
        'Safari' => '/\bVersion\/[\d.]+.*Safari\//',
    ];

    private const PLATFORMS = [
        'iPadOS' => '/\biPad\b/',
        'iOS' => '/\b(?:iPhone|iPod)\b/',
        'Android' => '/\bAndroid\b/',
        'ChromeOS' => '/\bCrOS\b/',
        'Windows' => '/\bWindows\b/',
        'macOS' => '/\bMac OS X\b|\bMacintosh\b/',
        'Linux' => '/\bLinux\b/',
    ];

    private function __construct(
        public readonly ?string $browser,
        public readonly ?string $platform,
        public readonly string $type,
    ) {}

    public static function parse(?string $userAgent): self
    {
        $userAgent ??= '';

        $browser = null;
        foreach (self::BROWSERS as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $browser = $name;
                break;
            }
        }

        $platform = null;
        foreach (self::PLATFORMS as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $platform = $name;
                break;
            }
        }

        $type = match (true) {
            $platform === 'iPadOS', $platform === 'Android' && !str_contains($userAgent, 'Mobile') => self::TYPE_TABLET,
            $platform === 'iOS', $platform === 'Android', str_contains($userAgent, 'Mobile') => self::TYPE_MOBILE,
            $platform !== null => self::TYPE_DESKTOP,
            default => self::TYPE_UNKNOWN,
        };

        return new self($browser, $platform, $type);
    }

    /**
     * A short human label, or null when nothing could be detected.
     */
    public function label(): ?string
    {
        return match (true) {
            $this->browser !== null && $this->platform !== null => trans('profile.sessions.device_label', ['browser' => $this->browser, 'platform' => $this->platform]),
            default => $this->browser ?? $this->platform,
        };
    }
}
