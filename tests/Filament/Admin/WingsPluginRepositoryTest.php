<?php

use App\Models\Node;
use App\Repositories\Daemon\DaemonPluginRepository;
use App\Tests\Traits\FakesWingsPlugins;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

// The Wings plugin API client (pelican/wings contract v2, section A).

uses(FakesWingsPlugins::class);

beforeEach(function () {
    $this->node = Node::factory()->create();
    $this->repository = (new DaemonPluginRepository())->setNode($this->node);
});

it('refuses an empty or invalid plugin id before calling wings', function (string $id, string $call) {
    Http::fake();

    $calls = [
        'enable' => fn () => $this->repository->enable($id),
        'disable' => fn () => $this->repository->disable($id),
        'uninstall' => fn () => $this->repository->uninstall($id, true),
        'settings' => fn () => $this->repository->saveSettings($id, []),
        'install' => fn () => $this->repository->install($id),
        'update' => fn () => $this->repository->update($id),
        'approve' => fn () => $this->repository->approve('install', $id, 'token'),
        'source' => fn () => $this->repository->getSource($id),
    ];

    expect($calls[$call])->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'empty' => '',
    'dot' => '.hidden',
    'parent' => '..',
    'slash' => 'a/b',
    'backslash' => 'a\\b',
    'nested parent' => 'a..b',
    'newline' => "a\nb",
])->with(['enable', 'disable', 'uninstall', 'settings', 'install', 'update', 'approve', 'source']);

it('encodes plugin ids in the path', function () {
    Http::fake(['*' => Http::response(['status' => 'enabled'])]);

    $this->repository->enable('state log+1');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/plugins/state%20log%2B1/enable'));
});

it('reads the access level from the list', function () {
    Http::fake(['*/api/plugins' => $this->wingsList([$this->wingsPlugin('state-log')], 'signed')]);

    $list = $this->repository->getPlugins();

    expect($list['available'])->toBeTrue()
        ->and($list['panel_access'])->toBe('signed')
        ->and($list['data'])->toHaveCount(1);
});

it('treats a 403 for the access level as not available', function () {
    Http::fake(['*/api/plugins' => $this->wingsForbidden($this->node, 'off', 'view')]);

    $list = $this->repository->getPlugins();

    expect($list['available'])->toBeFalse()
        ->and($list['reason'])->toBe('access')
        ->and($list['panel_access'])->toBe('off')
        ->and($list['required'])->toBe('view');
});

it('treats a 404 from wings as a wings without the plugin api', function () {
    Http::fake(['*/api/plugins' => $this->wingsNotFound($this->node)]);

    $list = $this->repository->getPlugins();

    expect($list['available'])->toBeFalse()
        ->and($list['reason'])->toBe('unsupported');
});

it('does not treat a 403 that did not come from wings as an access level', function () {
    Http::fake(['*/api/plugins' => Http::response(['panel_access' => 'off', 'required' => 'view'], 403, ['User-Agent' => 'nginx'])]);

    $this->repository->getPlugins();
})->throws(RequestException::class);

it('stages an install and approves it on the same endpoint', function () {
    Http::fake(['*/api/plugins/state-log/install' => Http::sequence()
        ->push($this->wingsStagedBody('install', 'state-log', 'tok-1'), 202)
        ->push(['status' => 'installed', 'plugin' => $this->wingsPlugin('state-log')]),
    ]);

    $staged = $this->repository->install('state-log', 'pelican');

    expect($staged['status'])->toBe('approval_required')
        ->and($staged['approval_token'])->toBe('tok-1');

    $installed = $this->repository->approve('install', 'state-log', 'tok-1');

    expect($installed['status'])->toBe('installed');

    $sent = Http::recorded()->map(fn (array $pair) => [$pair[0]->url(), $pair[0]->data()])->all();

    expect($sent)->toHaveCount(2)
        ->and($sent[0][1])->toBe(['registry' => 'pelican'])
        ->and($sent[1][1])->toBe(['approve' => 'tok-1'])
        ->and($sent[0][0])->toEndWith('/api/plugins/state-log/install')
        ->and($sent[1][0])->toEndWith('/api/plugins/state-log/install');
});

it('approves an import on the import endpoint', function () {
    Http::fake(['*/api/plugins/import' => Http::sequence()
        ->push($this->wingsStagedBody('import', 'state-log', 'tok-2'), 202)
        ->push(['status' => 'installed', 'plugin' => $this->wingsPlugin('state-log')]),
    ]);

    $this->repository->importFromUrl('https://example.test/state-log.tar.gz', str_repeat('a', 64));
    $this->repository->approve('import', null, 'tok-2');

    $bodies = Http::recorded()->map(fn (array $pair) => $pair[0]->data())->all();

    expect($bodies[0])->toBe(['url' => 'https://example.test/state-log.tar.gz', 'sha256' => str_repeat('a', 64)])
        ->and($bodies[1])->toBe(['approve' => 'tok-2']);
});

it('refuses a stage without an approval token', function () {
    Http::fake(['*' => Http::response(['status' => 'approval_required'], 202)]);

    $this->repository->update('state-log');
})->throws(InvalidArgumentException::class);

it('passes an up to date answer through', function () {
    Http::fake(['*' => Http::response(['status' => 'up_to_date', 'installed' => '1.2.0', 'latest' => '1.2.0'])]);

    expect($this->repository->update('state-log')['status'])->toBe('up_to_date');
});
