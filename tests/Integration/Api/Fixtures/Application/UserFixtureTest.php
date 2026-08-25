<?php

namespace App\Tests\Integration\Api\Fixtures\Application;

use App\Models\ApiKey;
use App\Models\User;
use App\Services\Acl\Api\AdminAcl;
use App\Tests\Integration\Api\Fixtures\FixtureTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('api-fixtures')]
class UserFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildTestFixture();
        $this->actingAsFixtureAdmin();
    }

    public function test_index(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/users'));
    }

    public function test_index_paginated(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/users?per_page=1&page=2'));
    }

    public function test_view(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/users/101'));
    }

    public function test_view_with_all_includes(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/users/101?include=servers,roles'));
    }

    public function test_view_external(): void
    {
        $this->assertFixtureSnapshot($this->getJson('/api/application/users/external/fixture-owner-ext'));
    }

    public function test_denied_include_returns_null_resource(): void
    {
        $this->createNewReadOnlyUsersKey();

        $this->assertFixtureSnapshot($this->getJson('/api/application/users/101?include=servers,roles'));
    }

    private function createNewReadOnlyUsersKey(): void
    {
        $key = ApiKey::factory()->create([
            'id' => 110,
            'user_id' => $this->fixtureAdmin->id,
            'key_type' => ApiKey::TYPE_APPLICATION,
            'identifier' => 'papp_fixture0002',
            'token' => 'fixtureapplicationtokenvalue0002',
            'memo' => 'Fixture reduced key',
            'permissions' => [
                User::RESOURCE_NAME => AdminAcl::READ,
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $key->identifier . 'fixtureapplicationtokenvalue0002');
    }
}
