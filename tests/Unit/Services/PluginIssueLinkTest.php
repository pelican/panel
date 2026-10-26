<?php

namespace App\Tests\Unit\Services;

use App\Enums\PluginStatus;
use App\Models\Plugin;
use App\Services\Helpers\PluginIssueLink;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PluginIssueLinkTest extends TestCase
{
    #[DataProvider('repositoryProvider')]
    public function test_github_repository_is_normalized(mixed $url, ?string $expected): void
    {
        $this->assertSame($expected, PluginIssueLink::githubRepository($url));
    }

    public static function repositoryProvider(): array
    {
        return [
            'plain' => ['https://github.com/pelican/plugin', 'https://github.com/pelican/plugin'],
            'git suffix' => ['https://github.com/pelican/plugin.git', 'https://github.com/pelican/plugin'],
            'deep link' => ['https://github.com/pelican/plugin/tree/main/src', 'https://github.com/pelican/plugin'],
            'www' => ['https://www.github.com/pelican/plugin/', 'https://github.com/pelican/plugin'],
            'not github' => ['https://gitlab.com/pelican/plugin', null],
            'lookalike host' => ['https://github.com.evil.test/pelican/plugin', null],
            'owner only' => ['https://github.com/pelican', null],
            'null' => [null, null],
        ];
    }

    public function test_links_to_a_prefilled_github_issue(): void
    {
        $url = PluginIssueLink::for([
            'title' => 'It broke & stuff',
            'description' => 'Steps: click | then cry',
            'plugin_version' => '1.2.0',
            'panel_version' => '1.0.0',
            'include_environment' => false,
        ], ['repository_url' => 'https://github.com/pelican/plugin', 'url' => 'https://hub.example.test/plugins/plugin']);

        $this->assertStringStartsWith('https://github.com/pelican/plugin/issues/new?title=It%20broke%20%26%20stuff&body=', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('Steps: click | then cry', $query['body']);
        $this->assertStringContainsString('| Plugin version | 1.2.0 |', $query['body']);
        $this->assertStringContainsString('| Panel version | 1.0.0 |', $query['body']);
        $this->assertStringNotContainsString('PHP version', $query['body']);
    }

    public function test_environment_details_are_included_when_asked(): void
    {
        $plugin = new Plugin();
        $plugin->url = 'https://github.com/pelican/plugin';
        $plugin->status = PluginStatus::Errored;
        $plugin->status_message = "Class not found\nin | file";

        $url = PluginIssueLink::for(['title' => 't', 'description' => 'd', 'include_environment' => true], null, $plugin);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('| PHP version | ' . PHP_VERSION . ' |', $query['body']);
        $this->assertStringContainsString('| Plugin status | errored |', $query['body']);
        $this->assertStringContainsString('| Status message | Class not found in \\| file |', $query['body']);
    }

    public function test_falls_back_to_the_hub_page_without_a_public_repository(): void
    {
        $url = PluginIssueLink::for(['title' => 't', 'description' => 'd'], ['repository_url' => null, 'url' => 'https://hub.example.test/plugins/plugin']);

        $this->assertSame('https://hub.example.test/plugins/plugin', $url);
    }

    public function test_is_null_when_there_is_nowhere_to_report(): void
    {
        $this->assertNull(PluginIssueLink::for(['title' => 't', 'description' => 'd'], ['repository_url' => null, 'url' => 'javascript:alert(1)']));
        $this->assertNull(PluginIssueLink::for(['title' => 't', 'description' => 'd']));
    }

    public function test_long_descriptions_are_trimmed_to_fit_but_keep_the_versions(): void
    {
        $url = PluginIssueLink::for([
            'title' => 'Long',
            'description' => str_repeat('ü', 4000),
            'plugin_version' => '9.9.9',
        ], ['repository_url' => 'https://github.com/pelican/plugin']);

        $this->assertLessThanOrEqual(PluginIssueLink::MAX_URL_LENGTH, strlen($url));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('| Plugin version | 9.9.9 |', $query['body']);
        $this->assertTrue(mb_check_encoding($query['body'], 'UTF-8'));
    }
}
