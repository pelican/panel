<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class BackupSettings extends Settings
{
    public int $backup_throttle_limit;

    public int $backup_throttle_period;

    public static function group(): string
    {
        return 'backup';
    }
}
