<?php

use App\Services\Helpers\SoftwareVersionService;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => cache()->flush());

it('caches a failed github lookup briefly instead of retrying every call', function () {
    Http::fake(['api.github.com/*' => Http::response(null, 500)]);
    $service = new SoftwareVersionService();

    expect($service->latestPanelVersion())->toBeNull()
        ->and($service->latestPanelVersion())->toBeNull()
        ->and($service->latestPanelVersionChangelog())->toBeNull();
    Http::assertSentCount(1);

    $this->travel(6)->minutes();
    $service->latestPanelVersion();
    Http::assertSentCount(2);
});

it('shares one github request for the panel version and changelog', function () {
    Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v1.2.3', 'body' => 'notes'])]);
    $service = new SoftwareVersionService();

    expect($service->latestPanelVersion())->toBe('1.2.3')
        ->and($service->latestPanelVersionChangelog())->toBe('notes');
    Http::assertSentCount(1);
});
