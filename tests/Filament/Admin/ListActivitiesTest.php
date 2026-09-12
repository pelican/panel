<?php

use App\Facades\Activity;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Models\ActivityLog;
use App\Models\Role;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('admin')));
afterEach(fn () => Filament::setCurrentPanel(null));

it('root admin can see activity from every panel', function () {
    [$admin, $server] = generateTestAccount([]);
    $admin = $admin->syncRoles(Role::getRootAdmin());

    Activity::event('auth:success')->actor($admin)->log();
    Activity::event('server:power.start')->subject($server)->log();

    $this->actingAs($admin);
    livewire(ListActivities::class)
        ->assertSuccessful()
        ->assertCountTableRecords(ActivityLog::count())
        ->assertCanSeeTableRecords(ActivityLog::all());
});

it('event filter narrows the table', function () {
    [$admin] = generateTestAccount([]);
    $admin = $admin->syncRoles(Role::getRootAdmin());

    Activity::event('auth:success')->actor($admin)->log();
    Activity::event('auth:fail')->log();

    $this->actingAs($admin);
    livewire(ListActivities::class)
        ->filterTable('event', 'auth:fail')
        ->assertCountTableRecords(1);
});

it('user without view activityLog is forbidden', function () {
    $role = Role::factory()->create(['name' => 'IP Viewer', 'guard_name' => 'web']);
    // seeIps alone must not grant access to the viewer.
    $role->givePermissionTo(Permission::findOrCreate('seeIps activityLog', 'web'));
    [$user] = generateTestAccount([]);
    $user = $user->syncRoles($role);

    $this->actingAs($user);
    livewire(ListActivities::class)
        ->assertForbidden();
});

it('user with view activityLog can see the viewer', function () {
    $role = Role::factory()->create(['name' => 'Auditor', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate('view activityLog', 'web'));
    [$user] = generateTestAccount([]);
    $user = $user->syncRoles($role);

    Activity::event('auth:success')->log();

    $this->actingAs($user);
    livewire(ListActivities::class)
        ->assertSuccessful()
        ->assertCountTableRecords(ActivityLog::count());
});

it('user with view activityLog can open the properties modal', function () {
    $role = Role::factory()->create(['name' => 'Modal Auditor', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate('view activityLog', 'web'));
    [$user] = generateTestAccount([]);
    $user = $user->syncRoles($role);

    $log = Activity::event('auth:success')->log();

    $this->actingAs($user);
    livewire(ListActivities::class)
        ->callAction(TestAction::make('view')->table($log))
        ->assertHasNoActionErrors();
});
