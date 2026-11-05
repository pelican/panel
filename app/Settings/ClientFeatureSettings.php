<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ClientFeatureSettings extends Settings
{
    public bool $panel_client_allocations_enabled;

    public bool $panel_client_allocations_create_new;

    public ?int $panel_client_allocations_range_start;

    public ?int $panel_client_allocations_range_end;

    public bool $panel_send_account_created_notification;

    public bool $panel_send_added_to_server_notification;

    public bool $panel_send_removed_from_server_notification;

    public bool $panel_send_install_notification;

    public bool $panel_send_reinstall_notification;

    public bool $panel_editable_server_descriptions;

    public static function group(): string
    {
        return 'client_features';
    }
}
