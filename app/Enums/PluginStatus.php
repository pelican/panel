<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PluginStatus: string implements HasColor, HasIcon, HasLabel
{
    case NotInstalled = 'not_installed';
    case Disabled = 'disabled';
    case Enabled = 'enabled';
    case Errored = 'errored';
    case Incompatible = 'incompatible';
    case Crashed = 'crashed';
    // A node that doesn't let the Panel see its Wings plugins (access level, or no plugin API)
    case Unavailable = 'unavailable';

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::NotInstalled => TablerIcon::HeartOff,
            self::Disabled => TablerIcon::HeartX,
            self::Enabled => TablerIcon::HeartCheck,
            self::Errored => TablerIcon::HeartBroken,
            self::Incompatible => TablerIcon::HeartCancel,
            self::Crashed => TablerIcon::HeartBolt,
            self::Unavailable => TablerIcon::LockAccess,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotInstalled => 'gray',
            self::Disabled => 'warning',
            self::Enabled => 'success',
            self::Errored => 'danger',
            self::Incompatible => 'danger',
            self::Crashed => 'danger',
            self::Unavailable => 'gray',
        };
    }

    public function getLabel(): string
    {
        return trans('admin/plugin.status_enum.' . $this->value);
    }
}
