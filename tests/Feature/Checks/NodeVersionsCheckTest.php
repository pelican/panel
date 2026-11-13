<?php

use App\Checks\NodeVersionsCheck;
use App\Models\Node;
use App\Services\Helpers\SoftwareVersionService;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Enums\Status;

beforeEach(function () {
    cache()->flush();
    config()->set('panel.wings.min_version', '1.0.0');
});

it('checks wings compatibility against the configured minimum', function (string $version, bool $expected) {
    expect(app(SoftwareVersionService::class)->isCompatibleWings($version))->toBe($expected);
})->with([
    'develop' => ['develop', true],
    'equal to minimum' => ['1.0.0', true],
    'newer patch' => ['1.0.1', true],
    'newer major' => ['2.0.0', true],
    'release candidate of minimum' => ['1.0.0-rc.1', false],
    'older' => ['0.9.0', false],
]);

it('honours a config override of the minimum', function () {
    config()->set('panel.wings.min_version', '1.2.0');

    expect(app(SoftwareVersionService::class)->isCompatibleWings('1.1.9'))->toBeFalse()
        ->and(app(SoftwareVersionService::class)->isCompatibleWings('1.2.0'))->toBeTrue();
});

it('reports node versions by compatibility', function (array $versions, string $status) {
    $fakes = ['api.github.com/*' => Http::response(['tag_name' => 'v1.1.0'])];

    foreach ($versions as $version) {
        $node = Node::factory()->create();
        $fakes["$node->fqdn*"] = Http::response(
            ['architecture' => 'amd64', 'cpu_count' => 1, 'kernel_version' => '6', 'os' => 'linux', 'version' => $version],
            headers: ['User-Agent' => "Pelican Wings/v$version (id:$node->daemon_token_id)"],
        );
    }
    $unreachable = Node::factory()->create();
    $fakes["$unreachable->fqdn*"] = Http::response(null, 500);

    Http::fake($fakes);

    expect(app(NodeVersionsCheck::class)->run()->status)->toEqual(Status::from($status));
})->with([
    'all latest' => [['1.1.0', '1.1.0'], 'ok'],
    'outdated but compatible' => [['1.1.0', '1.0.0'], 'warning'],
    'incompatible' => [['1.1.0', '0.9.0'], 'failed'],
]);
