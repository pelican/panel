<?php

use App\Models\ApiKey;
use App\Models\User;

it('finds a key by its full token', function () {
    $key = ApiKey::factory()->for(User::factory())->create(['token' => str_repeat('a', ApiKey::KEY_LENGTH)]);

    expect(ApiKey::findToken($key->identifier . str_repeat('a', ApiKey::KEY_LENGTH))?->is($key))->toBeTrue();
});

it('does not find a key with a wrong or partial secret', function (string $secret) {
    $key = ApiKey::factory()->for(User::factory())->create(['token' => str_repeat('a', ApiKey::KEY_LENGTH)]);

    expect(ApiKey::findToken($key->identifier . $secret))->toBeNull();
})->with([
    'wrong secret' => str_repeat('b', ApiKey::KEY_LENGTH),
    'prefix of the secret' => str_repeat('a', ApiKey::KEY_LENGTH - 1),
    'no secret' => '',
]);
