<?php

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(fn () => Filament::setCurrentPanel(Filament::getPanel('admin')));
afterEach(fn () => Filament::setCurrentPanel(null));

it('shows the correct account suspension action to a root administrator', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(Role::getRootAdmin());
    $target = User::factory()->create();
    $this->actingAs($admin);

    livewire(EditUser::class, ['record' => $target->getKey()])
        ->assertActionVisible(TestAction::make('suspendAccount'))
        ->assertActionHidden(TestAction::make('unsuspendAccount'));

    $target->forceFill(['suspended_at' => now()])->save();

    livewire(EditUser::class, ['record' => $target->getKey()])
        ->assertActionHidden(TestAction::make('suspendAccount'))
        ->assertActionVisible(TestAction::make('unsuspendAccount'));
});
