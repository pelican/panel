<?php

use App\Enums\RolePermissionModels;
use App\Filament\Admin\Resources\Servers\Pages\ListServers;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Pest\Livewire\livewire;

beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('admin')));
afterEach(fn () => Filament::setCurrentPanel(null));

it('does not query per row for a non root admin', function () {
    $role = Role::factory()->create(['name' => 'Server Ops', 'guard_name' => 'web']);
    foreach ([RolePermissionModels::Server, RolePermissionModels::Node, RolePermissionModels::Egg, RolePermissionModels::User] as $model) {
        $role->givePermissionTo(Permission::findOrCreate($model->viewAny(), 'web'), Permission::findOrCreate($model->update(), 'web'));
    }

    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles($role);
    $this->actingAs($admin);

    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        livewire(ListServers::class)->assertSuccessful();

        return count(DB::getQueryLog());
    };

    createServerModel();
    $count(); // Warm the permission and settings caches.
    $baseline = $count();

    foreach (range(1, 9) as $ignored) {
        createServerModel();
    }

    expect($count())->toBe($baseline);
});
