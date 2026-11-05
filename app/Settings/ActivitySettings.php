<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ActivitySettings extends Settings
{
    public int $app_activity_prune_days;

    public bool $app_activity_hide_admin;

    public int $app_webhook_prune_days;

    public static function group(): string
    {
        return 'activity';
    }
}
