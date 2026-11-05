<?php

use App\Settings\ActivitySettings;
use App\Settings\BackupSettings;
use App\Settings\ClientFeatureSettings;
use App\Settings\GeneralSettings;
use App\Settings\KeyValueStore;
use App\Settings\MailSettings;
use App\Settings\PluginHubSettings;
use App\Settings\RateLimitSettings;
use App\Settings\SettingsMap;
use Illuminate\Support\Facades\DB;

function reloadSettings(): void
{
    app()->forgetScopedInstances();
    app()->forgetInstance(KeyValueStore::class);
    cache()->flush();
}

it('round-trips every settings group through the store and the config overlay', function (string $env, mixed $value, string $configKey) {
    SettingsMap::save([$env => $value]);

    config()->set($configKey, 'stale');
    reloadSettings();
    SettingsMap::apply();

    expect(config($configKey))->toBe($value);
})->with([
    GeneralSettings::class => ['APP_NAME', 'Stored Panel', 'app.name'],
    MailSettings::class => ['MAIL_HOST', 'smtp.stored.test', 'mail.mailers.smtp.host'],
    BackupSettings::class => ['BACKUP_THROTTLE_LIMIT', 7, 'backups.throttles.limit'],
    ClientFeatureSettings::class => ['PANEL_SEND_INSTALL_NOTIFICATION', false, 'panel.email.send_install_notification'],
    ActivitySettings::class => ['APP_ACTIVITY_PRUNE_DAYS', 12, 'activity.prune_days'],
    RateLimitSettings::class => ['APP_API_CLIENT_RATELIMIT', 99, 'http.rate_limit.client'],
    PluginHubSettings::class => ['PANEL_PLUGIN_HUB_URL', 'https://hub.stored.test', 'panel.plugin.hub_url'],
]);

it('casts form values to the property types and keeps unsaved properties', function () {
    config()->set('mail.mailers.smtp.host', 'keep.me');

    $rest = SettingsMap::save(['MAIL_PORT' => '2525', 'TRUSTED_PROXIES' => '10.0.0.1,10.0.0.2', 'NOT_A_SETTING' => 'x']);

    reloadSettings();
    expect(app(MailSettings::class)->mail_port)->toBe(2525)
        ->and(app(MailSettings::class)->mail_host)->toBe('keep.me')
        ->and(config('trustedproxy.proxies'))->toBe(['10.0.0.1', '10.0.0.2'])
        ->and($rest)->toBe(['NOT_A_SETTING' => 'x']);
});

it('encrypts secret settings at rest', function () {
    SettingsMap::save(['MAIL_PASSWORD' => 'hunter2-mail']);
    app(KeyValueStore::class)->set(['OAUTH_GITHUB_CLIENT_SECRET' => 'hunter2-oauth', 'OAUTH_GITHUB_ENABLED' => 'true']);

    $raw = DB::table('settings')->pluck('payload')->implode(' ');
    reloadSettings();

    expect($raw)->not->toContain('hunter2')
        ->and(app(MailSettings::class)->mail_password)->toBe('hunter2-mail')
        ->and(setting('OAUTH_GITHUB_CLIENT_SECRET'))->toBe('hunter2-oauth')
        ->and(setting('OAUTH_GITHUB_ENABLED'))->toBeTrue();
});

it('leaves config alone when nothing is stored', function () {
    config()->set('app.name', 'From Env');

    SettingsMap::apply();

    expect(config('app.name'))->toBe('From Env');
});

it('seeds the store from config and env on upgrade, and can be re-run', function () {
    $migration = require database_path('migrations/2026_11_04_120000_move_settings_to_database.php');
    config()->set('app.name', 'Upgraded Panel');
    config()->set('http.rate_limit.client', '300');
    putenv('OAUTH_GITHUB_CLIENT_ID=upgrade-client');

    try {
        $migration->up();
        $migration->up();
    } finally {
        putenv('OAUTH_GITHUB_CLIENT_ID');
    }

    reloadSettings();
    expect(app(GeneralSettings::class)->app_name)->toBe('Upgraded Panel')
        ->and(app(RateLimitSettings::class)->app_api_client_ratelimit)->toBe(300)
        ->and(setting('OAUTH_GITHUB_CLIENT_ID'))->toBe('upgrade-client');
});
