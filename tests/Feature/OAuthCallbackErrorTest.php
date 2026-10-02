<?php

use Illuminate\Support\Facades\Exceptions;

beforeEach(function () {
    putenv('OAUTH_GITHUB_ENABLED=true');
    $_ENV['OAUTH_GITHUB_ENABLED'] = 'true';
});

afterEach(function () {
    putenv('OAUTH_GITHUB_ENABLED');
    unset($_ENV['OAUTH_GITHUB_ENABLED']);
});

it('reports a provider error as one short line', function () {
    Exceptions::fake();

    $forged = "denied\n[2026-01-01 00:00:00] production.CRITICAL: forged entry\n" . str_repeat('x', 5000);

    $this->get('/auth/oauth/callback/github?' . http_build_query(['error' => 'access_denied', 'error_description' => $forged]))
        ->assertRedirect();

    $messages = collect(Exceptions::reported())->map(fn (Throwable $e) => $e->getMessage());

    expect($messages)->toHaveCount(1)
        ->and($messages->first())->not->toContain("\n")
        ->and(strlen($messages->first()))->toBeLessThan(300);
});
