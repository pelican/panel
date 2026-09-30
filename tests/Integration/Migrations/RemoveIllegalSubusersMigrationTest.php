<?php

namespace App\Tests\Integration\Migrations;

use App\Models\Subuser;
use App\Models\User;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Support\Facades\Schema;

class RemoveIllegalSubusersMigrationTest extends IntegrationTestCase
{
    public function test_only_subusers_of_their_own_server_are_removed(): void
    {
        User::factory()->count(3)->create();
        $server = $this->createServerModel();
        $this->assertNotSame($server->id, $server->owner_id);

        $illegal = Subuser::factory()->create(['user_id' => $server->owner_id, 'server_id' => $server->id]);

        // The old query matched any subuser whose user id equals the id of a server owned by a user
        // whose id equals the subuser's server id, which says nothing about ownership.
        $coincidental = Schema::withoutForeignKeyConstraints(fn () => Subuser::factory()->create([
            'user_id' => $server->id,
            'server_id' => $server->owner_id,
        ]));

        (require database_path('migrations/2024_12_02_013000_remove_illegal_subusers.php'))->up();

        $this->assertDatabaseMissing('subusers', ['id' => $illegal->id]);
        $this->assertDatabaseHas('subusers', ['id' => $coincidental->id]);
    }
}
