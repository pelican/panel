<?php

use App\Filament\Server\Resources\Activities\Pages\ListActivities;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(fn () => Filament::setCurrentPanel('server'));

afterEach(fn () => Filament::setCurrentPanel(null));

function createServerActivity(Server $server, ?User $actor): ActivityLog
{
    $log = new ActivityLog([
        'event' => 'server:file.read',
        'ip' => '127.0.0.1',
        'properties' => [],
    ]);

    if ($actor) {
        $log->actor()->associate($actor);
    }

    $log->save();

    $log->subjects()->create([
        'subject_id' => $server->id,
        'subject_type' => $server->getMorphClass(),
    ]);

    return $log;
}

it('hides activity from admins with a custom role when hide admin activity is enabled', function () {
    config()->set('activity.hide_admin_activity', true);

    [$user, $server] = generateTestAccount();

    /** @var User $admin */
    $admin = User::factory()->create();
    /** @var Role $role */
    $role = Role::factory()->create(['name' => 'Support', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate('view server', 'web'));
    $admin->syncRoles($role);

    $adminLog = createServerActivity($server, $admin);
    $ownerLog = createServerActivity($server, $user);

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$ownerLog])
        ->assertCanNotSeeTableRecords([$adminLog]);
});

it('shows admin activity when hide admin activity is disabled', function () {
    config()->set('activity.hide_admin_activity', false);

    [$user, $server] = generateTestAccount();

    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles(Role::getRootAdmin());

    $adminLog = createServerActivity($server, $admin);

    $this->actingAs($user);
    Filament::setTenant($server);

    livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$adminLog]);
});

it('does not query per row', function () {
    [$user, $server] = generateTestAccount();

    $this->actingAs($user);
    Filament::setTenant($server);

    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        livewire(ListActivities::class)->assertSuccessful();

        return count(DB::getQueryLog());
    };

    createServerActivity($server, User::factory()->create());
    $count(); // Warm the permission and settings caches.
    $baseline = $count();

    foreach (range(1, 10) as $ignored) {
        createServerActivity($server, User::factory()->create());
    }

    expect($count())->toBe($baseline);
});

it('lists only actors of this server in the user filter', function () {
    [$user, $server] = generateTestAccount();
    $otherServer = createServerModel();

    $actor = User::factory()->create();
    $stranger = User::factory()->create();
    createServerActivity($server, $actor);
    createServerActivity($server, null);
    createServerActivity($otherServer, $stranger);

    $this->actingAs($user);
    Filament::setTenant($server);

    $options = livewire(ListActivities::class)->instance()->getTable()->getFilter('actor_id')->getOptions();

    expect($options)->toEqual([$actor->id => $actor->username]);
});
