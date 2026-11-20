<?php

namespace App\Tests\Integration\Services\Nodes;

use App\Models\Node;
use App\Models\User;
use App\Services\Nodes\NodeAutoDeployService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;

class NodeAutoDeployServiceTest extends IntegrationTestCase
{
    private function command(mixed $pluginAccess): string
    {
        config()->set('panel.plugin.wings_access', $pluginAccess);

        $user = User::factory()->create();
        $request = Request::create('https://panel.example.test/admin/nodes', 'GET');
        $request->setUserResolver(fn () => $user);

        return (string) $this->app->make(NodeAutoDeployService::class)->handle($request, Node::factory()->create());
    }

    public function test_the_command_has_no_plugin_access_flag_when_the_setting_is_not_set(): void
    {
        $this->assertStringNotContainsString('--plugin-access', $this->command(null));
    }

    /** The flag is how the Panel gives a new node its level (pelican/wings contract v2, L). */
    #[DataProvider('levels')]
    public function test_the_command_sets_the_plugin_access_level(string $level): void
    {
        $this->assertStringEndsWith(" --plugin-access $level", $this->command($level));
    }

    /** @return array<string, array{string}> */
    public static function levels(): array
    {
        return ['off' => ['off'], 'view' => ['view'], 'signed' => ['signed'], 'full' => ['full']];
    }

    /** An empty .env value, a typo or anything else never reaches the command. */
    #[DataProvider('invalid')]
    public function test_an_invalid_level_is_left_out(mixed $level): void
    {
        $this->assertStringNotContainsString('--plugin-access', $this->command($level));
    }

    /** @return array<string, array{mixed}> */
    public static function invalid(): array
    {
        return [
            'empty' => [''],
            'unknown' => ['root'],
            'upper case' => ['FULL'],
            'injection' => ['full; rm -rf /'],
            'bool' => [true],
        ];
    }
}
