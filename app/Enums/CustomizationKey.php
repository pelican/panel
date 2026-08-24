<?php

namespace App\Enums;

enum CustomizationKey: string
{
    case ConsoleRows = 'console_rows';
    case ConsoleFont = 'console_font';
    case ConsoleFontSize = 'console_font_size';
    case ConsoleGraphPeriod = 'console_graph_period';
    case TopNavigation = 'top_navigation';
    case DashboardLayout = 'dashboard_layout';

    case ButtonStyle = 'button_style';
    case RedirectToAdmin = 'redirect_to_admin';

    case ServersPerPage = 'servers_per_page';

    public function getDefaultValue(): string|int|bool
    {
        return match ($this) {
            self::ConsoleRows => 30,
            self::ConsoleFont => 'monospace',
            self::ConsoleFontSize => 14,
            self::ConsoleGraphPeriod => 30,
            self::TopNavigation => config('panel.filament.default-navigation', 'sidebar'),
            self::DashboardLayout => 'grid',
            self::ButtonStyle => true,
            self::RedirectToAdmin => false,
            // 0 means "unset", the table falls back to its contextual default (see ListServers).
            self::ServersPerPage => 0,
        };
    }

    /** @return array<string, string|int|bool> */
    public static function getDefaultCustomization(): array
    {
        $default = [];

        foreach (self::cases() as $key) {
            $default[$key->value] = $key->getDefaultValue();
        }

        return $default;
    }
}
