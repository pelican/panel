<?php

namespace App\Services\Helpers;

use App\Models\Plugin;

/**
 * Builds a link that lets an admin report a plugin issue to its author: a
 * pre-filled GitHub issue when the plugin has a public GitHub repository, or
 * the plugin's Hub page (which has its own report form) otherwise.
 */
class PluginIssueLink
{
    // Browsers and GitHub both start rejecting urls past roughly 8k characters.
    public const MAX_URL_LENGTH = 8000;

    /**
     * @param  array{title?: ?string, description?: ?string, plugin_version?: ?string, panel_version?: ?string, include_environment?: bool}  $data
     * @param  array<string, mixed>|null  $hubPlugin
     */
    public static function for(array $data, ?array $hubPlugin = null, ?Plugin $plugin = null): ?string
    {
        $repository = self::githubRepository($hubPlugin['repository_url'] ?? null) ?? self::githubRepository($plugin?->url);

        if ($repository !== null) {
            return self::githubIssueUrl($repository, $data, $plugin);
        }

        $hubPage = $hubPlugin['url'] ?? null;

        return is_string($hubPage) && str_starts_with($hubPage, 'https://') ? $hubPage : null;
    }

    /**
     * The canonical `https://github.com/{owner}/{repo}` for a GitHub repository url, or null.
     */
    public static function githubRepository(mixed $url): ?string
    {
        if (!is_string($url) || !preg_match('#^https?://(?:www\.)?github\.com/([A-Za-z0-9-]+)/([A-Za-z0-9._-]+?)(?:\.git)?(?:[/?\#].*)?$#i', trim($url), $matches)) {
            return null;
        }

        return "https://github.com/$matches[1]/$matches[2]";
    }

    /** @param  array{title?: ?string, description?: ?string, plugin_version?: ?string, panel_version?: ?string, include_environment?: bool}  $data */
    private static function githubIssueUrl(string $repository, array $data, ?Plugin $plugin): string
    {
        $base = "$repository/issues/new?title=" . rawurlencode(trim((string) ($data['title'] ?? ''))) . '&body=';

        $details = [
            '| | |',
            '|---|---|',
            '| Plugin version | ' . self::cell($data['plugin_version'] ?? null) . ' |',
            '| Panel version | ' . self::cell($data['panel_version'] ?? null) . ' |',
        ];

        if ($data['include_environment'] ?? false) {
            $details[] = '| PHP version | ' . PHP_VERSION . ' |';

            if ($plugin) {
                $details[] = '| Plugin status | ' . self::cell($plugin->status->value) . ' |';

                if (filled($plugin->status_message)) {
                    $details[] = '| Status message | ' . self::cell($plugin->status_message) . ' |';
                }
            }
        }

        $footer = "\n\n" . implode("\n", $details) . "\n\n_Reported from a Pelican Panel._";
        $description = trim((string) ($data['description'] ?? ''));

        // Trim the description, never the version details, until the url fits.
        $budget = self::MAX_URL_LENGTH - strlen($base) - strlen(rawurlencode($footer));
        while ($description !== '' && strlen(rawurlencode($description)) > $budget) {
            $description = mb_substr($description, 0, (int) floor(mb_strlen($description) * 0.9));
        }

        return $base . rawurlencode($description . $footer);
    }

    private static function cell(?string $value): string
    {
        $value = trim(str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], (string) $value));

        return $value === '' ? '-' : $value;
    }
}
