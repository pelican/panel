<?php

use App\Enums\SubuserPermission;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function restrictedAdmin(Node $node): User
{
    $role = Role::factory()->create(['name' => 'Admin ' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate('update server', 'web'));
    $role->nodes()->sync([$node->id]);

    $user = User::factory()->create();
    $user->syncRoles($role);

    return $user->fresh();
}

function countQueries(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();

    return count(DB::getQueryLog());
}

it('does not re-query node restrictions on every canTarget call', function () {
    $server = createServerModel();
    $other = Node::factory()->create();
    $admin = restrictedAdmin($server->node);

    expect($admin->canTarget($server->node))->toBeTrue()
        ->and($admin->canTarget($other))->toBeFalse();

    expect(countQueries(function () use ($admin, $server, $other) {
        foreach (range(1, 10) as $ignored) {
            $admin->canTarget($server->node);
            $admin->canTarget($other);
        }
    }))->toBe(0);
});

it('does not re-check admin access for every server permission', function () {
    $server = createServerModel();
    $admin = restrictedAdmin($server->node);
    $server->load('node');

    expect($admin->can(SubuserPermission::ControlConsole, $server))->toBeTrue();

    expect(countQueries(fn () => collect(SubuserPermission::cases())
        ->each(fn ($permission) => expect($admin->can($permission, $server))->toBeTrue())))->toBe(0);
});

it('checks ownership before admin access', function () {
    [$owner, $server] = generateTestAccount();
    $server->load(['node', 'subusers']);

    expect(countQueries(fn () => expect($owner->fresh()->can(SubuserPermission::ControlConsole, $server))->toBeTrue()))
        ->toBeLessThanOrEqual(1);
});

it('keeps node restrictions in force', function () {
    $server = createServerModel();
    $other = createServerModel(['node_id' => Node::factory()->create()->id]);
    $admin = restrictedAdmin($server->node);

    expect($admin->accessibleNodes()->pluck('id')->all())->toBe([$server->node_id])
        ->and($admin->can('update', $server))->toBeTrue()
        ->and($admin->can('update', $other))->toBeFalse()
        ->and($admin->can(SubuserPermission::ControlConsole, $other))->toBeFalse();
});
