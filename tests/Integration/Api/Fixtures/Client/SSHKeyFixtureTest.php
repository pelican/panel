<?php

namespace App\Tests\Integration\Api\Fixtures\Client;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class SSHKeyFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureOwner();
    }

    public function test_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/client/account/ssh-keys'));
    }
}
