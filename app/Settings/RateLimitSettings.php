<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class RateLimitSettings extends Settings
{
    public int $app_api_client_ratelimit;

    public int $app_api_client_ratelimit_period;

    public int $app_api_application_ratelimit;

    public int $app_api_application_ratelimit_period;

    public int $app_api_password_reset_ratelimit;

    public int $app_api_password_reset_ratelimit_period;

    public int $app_api_websocket_ratelimit;

    public int $app_api_websocket_ratelimit_period;

    public int $app_api_backup_restore_ratelimit;

    public int $app_api_backup_restore_ratelimit_period;

    public int $app_api_database_create_ratelimit;

    public int $app_api_database_create_ratelimit_period;

    public int $app_api_subuser_create_ratelimit;

    public int $app_api_subuser_create_ratelimit_period;

    public int $app_api_file_pull_ratelimit;

    public int $app_api_file_pull_ratelimit_period;

    public int $app_api_default_ratelimit;

    public int $app_api_default_ratelimit_period;

    public static function group(): string
    {
        return 'rate_limits';
    }
}
