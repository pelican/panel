<?php

namespace App\Tests\Integration\Api\Fixtures\Client;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class ApiKeyStoreFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureOwner();

        // The identifier and the meta.secret_token both come from Str::random, so pin
        // the generator to a fixed character before the request is made.
        Str::createRandomStringsUsing(fn ($length) => str_repeat('k', $length));
    }

    protected function tearDown(): void
    {
        Str::createRandomStringsUsing();

        parent::tearDown();
    }

    public function test_store(): void
    {
        $this->assertFixtureSnapshot($this->postJson('/api/client/account/api-keys', [
            'description' => 'Fixture mutation key',
            'allowed_ips' => ['192.0.2.1'],
        ]));
    }
}
