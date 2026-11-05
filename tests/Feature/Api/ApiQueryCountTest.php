<?php

use App\Models\ApiKey;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\Subuser;
use App\Models\User;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Support\Facades\DB;

/**
 * Counts the queries a request runs, so list endpoints can be checked for
 * queries that grow with the number of rows returned.
 */
function countApiQueries(Closure $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function useApplicationApiKey(): void
{
    $user = User::factory()->create();
    $user->syncRoles(Role::getRootAdmin());

    $permissions = [];
    foreach ([Server::RESOURCE_NAME, Node::RESOURCE_NAME, 'allocations', User::RESOURCE_NAME, 'eggs'] as $resource) {
        $permissions[$resource] = AdminAcl::READ;
    }

    $key = ApiKey::factory()->create(['user_id' => $user->id, 'key_type' => ApiKey::TYPE_APPLICATION, 'permissions' => $permissions]);

    test()->withHeader('Accept', 'application/vnd.panel.v1+json')
        ->withHeader('Authorization', 'Bearer ' . $key->identifier . $key->token);
}

it('does not query per server on the client server list', function () {
    $owner = User::factory()->create();
    $addServer = function () use ($owner) {
        $server = createServerModel(['owner_id' => $owner->id]);
        Subuser::query()->create(['user_id' => User::factory()->create()->id, 'server_id' => $server->id, 'permissions' => []]);
    };
    $addServer();

    $this->actingAs($owner);
    $request = fn () => $this->getJson('/api/client?include=egg,subusers')->assertOk();

    $request();
    $baseline = countApiQueries($request);

    foreach (range(1, 10) as $ignored) {
        $addServer();
    }

    expect(countApiQueries($request))->toBe($baseline);
});

it('does not query per server on the application server list', function () {
    useApplicationApiKey();
    createServerModel();

    $request = fn () => $this->getJson('/api/application/servers?include=allocations,user,node,egg,subusers,variables,databases')->assertOk();

    $request();
    $baseline = countApiQueries($request);

    foreach (range(1, 10) as $ignored) {
        createServerModel();
    }

    expect(countApiQueries($request))->toBe($baseline);
});

it('does not query per node on the application node list', function () {
    useApplicationApiKey();
    createServerModel();

    $request = fn () => $this->getJson('/api/application/nodes')->assertOk();

    $request();
    $baseline = countApiQueries($request);

    foreach (range(1, 10) as $ignored) {
        createServerModel();
    }

    expect(countApiQueries($request))->toBe($baseline);
});

it('does not query per user on the application user list', function () {
    useApplicationApiKey();

    $request = fn () => $this->getJson('/api/application/users')->assertOk();

    $request();
    $baseline = countApiQueries($request);

    User::factory()->count(10)->create();

    expect(countApiQueries($request))->toBe($baseline);
});

it('does not query per server on the remote server list', function () {
    $node = createServerModel()->node;

    $this->withHeader('Authorization', "Bearer $node->daemon_token_id." . $node->daemon_token);
    $request = fn () => $this->getJson('/api/remote/servers?per_page=50')->assertOk();

    $request();
    $baseline = countApiQueries($request);

    foreach (range(1, 10) as $ignored) {
        createServerModel(['node_id' => $node->id]);
    }

    expect(countApiQueries($request))->toBe($baseline);
});
