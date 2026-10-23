<?php

namespace App\Enums;

use BackedEnum;

enum SubuserPermission: string
{
    case WebsocketConnect = 'websocket.connect';

    case ControlConsole = 'control.console';
    case ControlStart = 'control.start';
    case ControlStop = 'control.stop';
    case ControlRestart = 'control.restart';

    case FileRead = 'file.read';
    case FileReadContent = 'file.read-content';
    case FileCreate = 'file.create';
    case FileUpdate = 'file.update';
    case FileDelete = 'file.delete';
    case FileArchive = 'file.archive';
    case FileSftp = 'file.sftp';

    case BackupRead = 'backup.read';
    case BackupCreate = 'backup.create';
    case BackupDelete = 'backup.delete';
    case BackupDownload = 'backup.download';
    case BackupRestore = 'backup.restore';

    case ScheduleRead = 'schedule.read';
    case ScheduleCreate = 'schedule.create';
    case ScheduleUpdate = 'schedule.update';
    case ScheduleDelete = 'schedule.delete';

    case UserRead = 'user.read';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDelete = 'user.delete';

    case DatabaseRead = 'database.read';
    case DatabaseCreate = 'database.create';
    case DatabaseUpdate = 'database.update';
    case DatabaseDelete = 'database.delete';
    case DatabaseViewPassword = 'database.view-password';

    case AllocationRead = 'allocation.read';
    case AllocationCreate = 'allocation.create';
    case AllocationUpdate = 'allocation.update';
    case AllocationDelete = 'allocation.delete';

    case ActivityRead = 'activity.read';

    case MountRead = 'mount.read';
    case MountUpdate = 'mount.update';

    case StartupRead = 'startup.read';
    case StartupUpdate = 'startup.update';
    case StartupDockerImage = 'startup.docker-image';

    case SettingsRename = 'settings.rename';
    case SettingsDescription = 'settings.description';
    case SettingsReinstall = 'settings.reinstall';
    case SettingsChangeIcon = 'settings.change-icon';

    public static function fromPowerSignal(string $signal): ?self
    {
        return match ($signal) {
            'start' => self::ControlStart,
            'restart' => self::ControlRestart,
            'stop', 'kill' => self::ControlStop,
            default => null,
        };
    }

    /** @return string[] */
    public function split(): array
    {
        return explode('.', $this->value, 2);
    }

    public function isHidden(): bool
    {
        return $this === self::WebsocketConnect;
    }

    public function getIcon(): ?BackedEnum
    {
        [$group, $permission] = $this->split();

        return match ($group) {
            'control' => TablerIcon::Terminal2,
            'user' => TablerIcon::Users,
            'file' => TablerIcon::Files,
            'backup' => TablerIcon::FileZip,
            'allocation' => TablerIcon::Network,
            'startup' => TablerIcon::PlayerPlay,
            'database' => TablerIcon::Database,
            'schedule' => TablerIcon::Clock,
            'settings' => TablerIcon::Settings,
            'activity' => TablerIcon::Stack,
            'mount' => TablerIcon::LayersLinked,
            default => null,
        };
    }
}
