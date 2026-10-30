<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

// pest()->extend(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\Subuser;
use App\Models\User;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toLogActivities', function (int $times, ?Model $actor = null) {
    // Scoped to an actor so anonymous audit rows from fixture setup don't count.
    $query = $actor ? ActivityLog::forActor($actor) : ActivityLog::query();

    expect($query->count())->toBe($times);
});

uses(IntegrationTestCase::class)->in('Feature', 'Filament');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Generates a user and a server for that user. If an array of permissions is passed it
 * is assumed that the user is actually a subuser of the server.
 *
 * @param  string[]  $permissions
 * @return array{User, Server}
 */

/**
 * Creates a server model in the databases for the purpose of testing. If an attribute
 * is passed in that normally requires this function to create a model no model will be
 * created and that attribute's value will be used.
 *
 * The returned server model will have all the relationships loaded onto it.
 */
function createServerModel(array $attributes = []): Server
{
    if (isset($attributes['user_id'])) {
        $attributes['owner_id'] = $attributes['user_id'];
    }

    if (!isset($attributes['owner_id'])) {
        /** @var User $user */
        $user = User::factory()->create();
        $attributes['owner_id'] = $user->id;
    }

    if (!isset($attributes['node_id'])) {
        /** @var Node $node */
        $node = Node::factory()->create();
        $attributes['node_id'] = $node->id;
    }

    if (!isset($attributes['allocation_id'])) {
        /** @var Allocation $allocation */
        $allocation = Allocation::factory()->create(['node_id' => $attributes['node_id']]);
        $attributes['allocation_id'] = $allocation->id;
    }

    if (empty($attributes['egg_id'])) {
        $egg = getBungeecordEgg();

        $attributes['egg_id'] = $egg->id;
    }

    unset($attributes['user_id']);

    /** @var Server $server */
    $server = Server::factory()->create($attributes);

    Allocation::query()->where('id', $server->allocation_id)->update(['server_id' => $server->id]);

    return $server->fresh([
        'user', 'node', 'allocation', 'egg',
    ]);
}

/**
 * Generates a user and a server for that user. If an array of permissions is passed it
 * is assumed that the user is actually a subuser of the server.
 *
 * @param  string[]  $permissions
 * @return array{User, Server}
 */
function generateTestAccount(array $permissions = []): array
{
    /** @var User $user */
    $user = User::factory()->create();

    if (empty($permissions)) {
        return [$user, createServerModel(['user_id' => $user->id])];
    }

    $server = createServerModel();

    Subuser::query()->create([
        'user_id' => $user->id,
        'server_id' => $server->id,
        'permissions' => $permissions,
    ]);

    return [$user, $server];
}

/**
 * Clones a given egg allowing us to make modifications that don't affect other
 * tests that rely on the egg existing in the correct state.
 */
function cloneEggAndVariables(Egg $egg): Egg
{
    $model = $egg->replicate(['id', 'uuid']);
    $model->uuid = Uuid::uuid4()->toString();
    $model->push();

    /** @var Egg $model */
    $model = $model->fresh();

    foreach ($egg->variables as $variable) {
        $variable->replicate(['id', 'egg_id'])->forceFill(['egg_id' => $model->id])->push();
    }

    return $model->fresh();
}

/**
 * Almost every test just assumes it is using BungeeCord — this is the critical
 * egg model for all tests unless specified otherwise.
 */
function getBungeecordEgg(): Egg
{
    /** @var Egg $egg */
    $egg = Egg::query()->where('author', 'panel@example.com')->where('name', 'Bungeecord')->firstOrFail();

    return $egg;
}
