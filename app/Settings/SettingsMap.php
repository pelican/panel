<?php

namespace App\Settings;

use BackedEnum;
use Illuminate\Support\Facades\App;
use ReflectionNamedType;
use ReflectionProperty;
use Spatie\LaravelSettings\Settings;
use Throwable;

/**
 * Maps each database-backed setting to the env key it replaced (also its form field name)
 * and the config key it overrides. The settings property is the lowercased env key.
 */
final class SettingsMap
{
    /** @var array<string, array{class-string<Settings>, string}> */
    public const MAP = [
        'APP_NAME' => [GeneralSettings::class, 'app.name'],
        'APP_LOGO' => [GeneralSettings::class, 'app.logo'],
        'APP_FAVICON' => [GeneralSettings::class, 'app.favicon'],
        'APP_DEBUG' => [GeneralSettings::class, 'app.debug'],
        'FILAMENT_AVATAR_PROVIDER' => [GeneralSettings::class, 'panel.filament.avatar-provider'],
        'FILAMENT_UPLOADABLE_AVATARS' => [GeneralSettings::class, 'panel.filament.uploadable-avatars'],
        'PANEL_USE_BINARY_PREFIX' => [GeneralSettings::class, 'panel.use_binary_prefix'],
        'FILAMENT_DEFAULT_NAVIGATION' => [GeneralSettings::class, 'panel.filament.default-navigation'],
        'APP_2FA_REQUIRED' => [GeneralSettings::class, 'panel.auth.2fa_required'],
        'FILAMENT_WIDTH' => [GeneralSettings::class, 'panel.filament.display-width'],
        'TRUSTED_PROXIES' => [GeneralSettings::class, 'trustedproxy.proxies'],
        'GUZZLE_TIMEOUT' => [GeneralSettings::class, 'panel.guzzle.timeout'],
        'GUZZLE_CONNECT_TIMEOUT' => [GeneralSettings::class, 'panel.guzzle.connect_timeout'],

        'MAIL_MAILER' => [MailSettings::class, 'mail.default'],
        'MAIL_FROM_ADDRESS' => [MailSettings::class, 'mail.from.address'],
        'MAIL_FROM_NAME' => [MailSettings::class, 'mail.from.name'],
        'MAIL_HOST' => [MailSettings::class, 'mail.mailers.smtp.host'],
        'MAIL_PORT' => [MailSettings::class, 'mail.mailers.smtp.port'],
        'MAIL_USERNAME' => [MailSettings::class, 'mail.mailers.smtp.username'],
        'MAIL_PASSWORD' => [MailSettings::class, 'mail.mailers.smtp.password'],
        'MAIL_SCHEME' => [MailSettings::class, 'mail.mailers.smtp.scheme'],
        'MAILGUN_DOMAIN' => [MailSettings::class, 'services.mailgun.domain'],
        'MAILGUN_SECRET' => [MailSettings::class, 'services.mailgun.secret'],
        'MAILGUN_ENDPOINT' => [MailSettings::class, 'services.mailgun.endpoint'],

        'BACKUP_THROTTLE_LIMIT' => [BackupSettings::class, 'backups.throttles.limit'],
        'BACKUP_THROTTLE_PERIOD' => [BackupSettings::class, 'backups.throttles.period'],

        'PANEL_CLIENT_ALLOCATIONS_ENABLED' => [ClientFeatureSettings::class, 'panel.client_features.allocations.enabled'],
        'PANEL_CLIENT_ALLOCATIONS_CREATE_NEW' => [ClientFeatureSettings::class, 'panel.client_features.allocations.create_new'],
        'PANEL_CLIENT_ALLOCATIONS_RANGE_START' => [ClientFeatureSettings::class, 'panel.client_features.allocations.range_start'],
        'PANEL_CLIENT_ALLOCATIONS_RANGE_END' => [ClientFeatureSettings::class, 'panel.client_features.allocations.range_end'],
        'PANEL_SEND_ACCOUNT_CREATED_NOTIFICATION' => [ClientFeatureSettings::class, 'panel.email.send_account_created_notification'],
        'PANEL_SEND_ADDED_TO_SERVER_NOTIFICATION' => [ClientFeatureSettings::class, 'panel.email.send_added_to_server_notification'],
        'PANEL_SEND_REMOVED_FROM_SERVER_NOTIFICATION' => [ClientFeatureSettings::class, 'panel.email.send_removed_from_server_notification'],
        'PANEL_SEND_INSTALL_NOTIFICATION' => [ClientFeatureSettings::class, 'panel.email.send_install_notification'],
        'PANEL_SEND_REINSTALL_NOTIFICATION' => [ClientFeatureSettings::class, 'panel.email.send_reinstall_notification'],
        'PANEL_EDITABLE_SERVER_DESCRIPTIONS' => [ClientFeatureSettings::class, 'panel.editable_server_descriptions'],

        'APP_ACTIVITY_PRUNE_DAYS' => [ActivitySettings::class, 'activity.prune_days'],
        'APP_ACTIVITY_HIDE_ADMIN' => [ActivitySettings::class, 'activity.hide_admin_activity'],
        'APP_WEBHOOK_PRUNE_DAYS' => [ActivitySettings::class, 'panel.webhook.prune_days'],

        'APP_API_CLIENT_RATELIMIT' => [RateLimitSettings::class, 'http.rate_limit.client'],
        'APP_API_CLIENT_RATELIMIT_PERIOD' => [RateLimitSettings::class, 'http.rate_limit.client_period'],
        'APP_API_APPLICATION_RATELIMIT' => [RateLimitSettings::class, 'http.rate_limit.application'],
        'APP_API_APPLICATION_RATELIMIT_PERIOD' => [RateLimitSettings::class, 'http.rate_limit.application_period'],
        'APP_API_PASSWORD_RESET_RATELIMIT' => [RateLimitSettings::class, 'http.rate_limit.password_reset'],
        'APP_API_PASSWORD_RESET_RATELIMIT_PERIOD' => [RateLimitSettings::class, 'http.rate_limit.password_reset_period'],
        'APP_API_WEBSOCKET_RATELIMIT' => [RateLimitSettings::class, 'http.rate_limit.websocket'],
        'APP_API_WEBSOCKET_RATELIMIT_PERIOD' => [RateLimitSettings::class, 'http.rate_limit.websocket_period'],
        'APP_API_BACKUP_RESTORE_RATELIMIT' => [RateLimitSettings::class, 'http.rate_limit.backup_restore'],
        'APP_API_BACKUP_RESTORE_RATELIMIT_PERIOD' => [RateLimitSettings::class, 'http.rate_limit.backup_restore_period'],
        'APP_API_DATABASE_CREATE_RATELIMIT' => [RateLimitSettings::class, 'http.rate_limit.database_create'],
        'APP_API_DATABASE_CREATE_RATELIMIT_PERIOD' => [RateLimitSettings::class, 'http.rate_limit.database_create_period'],
        'APP_API_SUBUSER_CREATE_RATELIMIT' => [RateLimitSettings::class, 'http.rate_limit.subuser_create'],
        'APP_API_SUBUSER_CREATE_RATELIMIT_PERIOD' => [RateLimitSettings::class, 'http.rate_limit.subuser_create_period'],
        'APP_API_FILE_PULL_RATELIMIT' => [RateLimitSettings::class, 'http.rate_limit.file_pull'],
        'APP_API_FILE_PULL_RATELIMIT_PERIOD' => [RateLimitSettings::class, 'http.rate_limit.file_pull_period'],
        'APP_API_DEFAULT_RATELIMIT' => [RateLimitSettings::class, 'http.rate_limit.default'],
        'APP_API_DEFAULT_RATELIMIT_PERIOD' => [RateLimitSettings::class, 'http.rate_limit.default_period'],

        'PANEL_PLUGIN_HUB_URL' => [PluginHubSettings::class, 'panel.plugin.hub_url'],
        'PANEL_PLUGIN_HUB_API_KEY' => [PluginHubSettings::class, 'panel.plugin.hub_api_key'],
    ];

    /**
     * Overlay every stored settings group onto config. A group without stored rows
     * (fresh database, before the upgrade migration) keeps its .env/config values.
     */
    public static function apply(): void
    {
        foreach (self::byClass() as $class => $keys) {
            try {
                $values = App::make($class)->toArray();
            } catch (Throwable) {
                continue;
            }

            foreach ($keys as $env => $configKey) {
                config()->set($configKey, self::toConfig($env, $values[strtolower($env)]));
            }
        }
    }

    /**
     * Persist env-keyed values (e.g. form state) into their settings groups and the running config.
     * Properties not present in $data keep their current value.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> the entries of $data that are not mapped settings
     */
    public static function save(array $data): array
    {
        foreach (self::byClass() as $class => $keys) {
            $changed = array_intersect_key($data, $keys);
            if ($changed === []) {
                continue;
            }

            $values = self::fromConfig($class);
            foreach ($changed as $env => $value) {
                $values[strtolower($env)] = self::cast($class, strtolower($env), $value);
                config()->set($keys[$env], self::toConfig($env, $values[strtolower($env)]));
            }

            (new $class($values))->save();
        }

        return array_diff_key($data, self::MAP);
    }

    /**
     * Current values of a settings group, read from config (which reflects .env before the
     * upgrade migration, and the stored settings after it).
     *
     * @param  class-string<Settings>  $class
     * @return array<string, mixed>
     */
    public static function fromConfig(string $class): array
    {
        $values = [];
        foreach (self::byClass()[$class] as $env => $configKey) {
            $values[strtolower($env)] = self::cast($class, strtolower($env), config($configKey));
        }

        return $values;
    }

    /** @return array<class-string<Settings>, array<string, string>> */
    private static function byClass(): array
    {
        $grouped = [];
        foreach (self::MAP as $env => [$class, $configKey]) {
            $grouped[$class][$env] = $configKey;
        }

        return $grouped;
    }

    /** Coerce a raw env/config/form value to the settings property's declared type. */
    private static function cast(string $class, string $property, mixed $value): mixed
    {
        $type = (new ReflectionProperty($class, $property))->getType();
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_array($value)) {
            $value = implode(',', array_filter($value));
        }

        if ($value === null || $value === '') {
            return $type->allowsNull() ? null : ($type->getName() === 'string' ? '' : $value);
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'string' => (string) $value,
            default => $value,
        };
    }

    /** Settings store the .env shape; the few config keys that parse it need the same parsing here. */
    private static function toConfig(string $env, mixed $value): mixed
    {
        if ($env === 'TRUSTED_PROXIES') {
            return in_array($value, ['*', '**'], true) ? $value : explode(',', $value ?? '');
        }

        return $value;
    }
}
