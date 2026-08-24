<?php

namespace App\Tests\Integration\Api\Fixtures\Client;

use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class ClientFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();

        // The factory stores rules as a pipe string but the model casts it to array,

        $this->actingAsFixtureOwner();
    }

    public function test_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/client'));
    }

    public function test_index_paginated(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/client?per_page=1&page=1'));
    }

    public function test_permissions(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/client/permissions'));
    }
}
