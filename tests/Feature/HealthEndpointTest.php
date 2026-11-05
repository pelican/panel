<?php

use App\Checks\DatabaseCheck;
use App\Checks\PanelVersionCheck;
use App\Http\Controllers\HealthController;
use Spatie\Health\Checks\Result;
use Spatie\Health\ResultStores\InMemoryHealthResultStore;
use Spatie\Health\ResultStores\ResultStore;

covers(HealthController::class);

beforeEach(function () {
    config(['health.endpoint_secret' => 'hunter2']);
    app()->instance(ResultStore::class, $this->store = new InMemoryHealthResultStore());
    $this->store->save(collect([
        Result::make()->check(DatabaseCheck::new())->ok(),
        Result::make()->check(PanelVersionCheck::new())->warning('outdated'),
    ]));
});

it('is disabled without a secret', function () {
    config(['health.endpoint_secret' => null]);

    $this->getJson('/health?token=')->assertNotFound();
});

it('rejects a missing or wrong token', function (string $uri) {
    $this->getJson($uri)->assertForbidden();
})->with(['/health', '/health?token=nope', '/health?token[]=hunter2']);

it('returns 200 when the worst check is a warning', function () {
    $this->getJson('/health', ['Authorization' => 'Bearer hunter2'])->assertOk()->assertJsonCount(2, 'checkResults');
    $this->getJson('/health?token=hunter2')->assertOk()->assertCookieMissing(config('session.cookie'));
});

it('returns 503 when a check failed', function () {
    $this->store->save(collect([Result::make()->check(DatabaseCheck::new())->failed('down')]));

    $this->getJson('/health?token=hunter2')->assertServiceUnavailable();
});

it('returns 503 when the results are stale', function () {
    $this->travel(11)->minutes();

    $this->getJson('/health?token=hunter2')->assertServiceUnavailable();
});
