<?php

namespace App\Tests\Integration\Api\Fixtures\Client;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class AccountFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureOwner();
    }

    public function test_view(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/client/account'));
    }

    public function test_activity(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/client/account/activity'));
    }

    public function test_activity_with_actor(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/client/account/activity?include=actor'));
    }
}
