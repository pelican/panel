<?php

namespace App\Services\Nodes;

use App\Models\Node;
use App\Models\User;
use App\Repositories\Daemon\DaemonPluginRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Installs, updates and imports Wings staged and that wait for the admin's approval (contract v3, A4).
 *
 * The approval token, node and endpoint stay on the server: the browser only gets a random handle,
 * tied to the admin who staged the plugin, so it can't send a token to another node or plugin.
 */
final class WingsPluginApprovals
{
    private const PREFIX = 'wings_plugins.approval.';

    /** Wings keeps a stage for 10 minutes. */
    private const MAX_MINUTES = 10;

    /**
     * Keeps a 202 answer and returns the handle to approve it with.
     *
     * @param  array<string, mixed>  $staged
     */
    public function put(User $user, Node $node, string $action, ?string $pluginId, array $staged): string
    {
        if (!in_array($action, DaemonPluginRepository::STAGED_ACTIONS, true)) {
            throw new InvalidArgumentException("Unknown plugin action \"$action\".");
        }

        $token = $staged['approval_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new InvalidArgumentException('The node staged the plugin but sent no approval token.');
        }

        $plugin = is_array($staged['plugin'] ?? null) ? $staged['plugin'] : [];
        $name = $plugin['name'] ?? null;

        // What Wings staged must be what was asked for (an import names its plugin from the archive)
        if (($staged['action'] ?? $action) !== $action || ($pluginId !== null && $name !== $pluginId) || !DaemonPluginRepository::isValidId($name)) {
            throw new InvalidArgumentException('The node staged something other than what was asked for.');
        }

        $handle = Str::random(40);
        $expiresAt = $this->expiry($staged['expires_at'] ?? null);

        cache()->put($this->key($user, $handle), [
            'node_id' => $node->id,
            'node_name' => $node->name,
            'action' => $action,
            'plugin_id' => $action === 'import' ? null : $pluginId,
            'token' => $token,
            'plugin' => [
                'name' => $name,
                'version' => self::string($plugin, 'version'),
                'author' => self::string($plugin, 'author'),
                'description' => self::string($plugin, 'description'),
                'capability' => self::string($plugin, 'capability'),
                'source' => self::string($plugin, 'source'),
                'entrypoint' => self::strings($plugin['entrypoint'] ?? []),
                'events' => self::strings($plugin['events'] ?? []),
            ],
            'host_access' => self::strings($staged['host_access'] ?? []),
            'replaces' => is_string($staged['replaces'] ?? null) && $staged['replaces'] !== '' ? $staged['replaces'] : null,
            'signed' => ($staged['signed'] ?? false) === true,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return $handle;
    }

    /**
     * What waits for approval under $handle, if it is $user's and hasn't expired.
     *
     * @return array<string, mixed>|null
     */
    public function get(User $user, string $handle): ?array
    {
        if (!self::isHandle($handle)) {
            return null;
        }

        $pending = cache()->get($this->key($user, $handle));

        return is_array($pending) ? $pending : null;
    }

    /**
     * Same as get(), but it can only be taken once.
     *
     * @return array<string, mixed>|null
     */
    public function pull(User $user, string $handle): ?array
    {
        if (!self::isHandle($handle)) {
            return null;
        }

        $pending = cache()->pull($this->key($user, $handle));

        return is_array($pending) ? $pending : null;
    }

    private static function isHandle(string $handle): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{40}$/', $handle);
    }

    private function key(User $user, string $handle): string
    {
        return self::PREFIX . $user->id . '.' . $handle;
    }

    /** When Wings drops the stage, but never later than 10 minutes from now. */
    private function expiry(mixed $expiresAt): CarbonImmutable
    {
        $latest = CarbonImmutable::now()->addMinutes(self::MAX_MINUTES);

        try {
            $at = is_string($expiresAt) ? CarbonImmutable::parse($expiresAt) : $latest;
        } catch (Throwable) {
            $at = $latest;
        }

        return $at->greaterThan($latest) || $at->isPast() ? $latest : $at;
    }

    /** @param  array<mixed>  $from */
    private static function string(array $from, string $key): ?string
    {
        $value = $from[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return string[] */
    private static function strings(mixed $list): array
    {
        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }
}
