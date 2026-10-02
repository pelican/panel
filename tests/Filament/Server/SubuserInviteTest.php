<?php

use App\Filament\Server\Resources\Subusers\Pages\ListSubusers;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(fn () => Filament::setCurrentPanel('server'));
afterEach(fn () => Filament::setCurrentPanel(null));

it('records the subuser invite in the activity log', function () {
    [$owner, $server] = generateTestAccount();
    $friend = User::factory()->create(['email' => 'friend@example.com']);

    $this->actingAs($owner);
    Filament::setTenant($server);

    livewire(ListSubusers::class)
        ->callAction(TestAction::make('invite')->table(), data: ['email' => 'friend@example.com'])
        ->assertHasNoActionErrors();

    expect($server->subusers()->where('user_id', $friend->id)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'server:subuser.create')->exists())->toBeTrue();
});
