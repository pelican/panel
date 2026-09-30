<?php

namespace App\Tests\Integration\Migrations;

use App\Models\Subuser;
use App\Models\User;
use App\Tests\Integration\IntegrationTestCase;

class RemoveIllegalSubusersMigrationTest extends IntegrationTestCase
{
    public function test_only_subusers_of_their_own_server_are_removed(): void
    {
        $server = $this->createServerModel(['id' => 9002]);
        $illegal = Subuser::factory()->create(['user_id' => $server->owner_id, 'server_id' => $server->id]);

        // The old query matched any subuser whose user id equals the id of a server owned by a user
        // whose id equals the subuser's server id, which says nothing about ownership.
        $subuser = User::factory()->create(['id' => 9001]);
        User::factory()->create(['id' => $server->id]);
        $this->createServerModel(['id' => $subuser->id, 'owner_id' => $server->id]);
        $coincidental = Subuser::factory()->create(['user_id' => $subuser->id, 'server_id' => $server->id]);

        (require database_path('migrations/2024_12_02_013000_remove_illegal_subusers.php'))->up();

        $this->assertDatabaseMissing('subusers', ['id' => $illegal->id]);
        $this->assertDatabaseHas('subusers', ['id' => $coincidental->id]);
    }
}
