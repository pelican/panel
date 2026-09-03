<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    config()->set('mail.from.address', 'support@example.com');
});

afterEach(fn () => Filament::setCurrentPanel(null));

it('shows the suspension message only after valid password credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('correct-password'),
        'suspended_at' => now(),
    ]);

    livewire(Login::class)
        ->fillForm([
            'login' => $user->email,
            'password' => 'correct-password',
        ])
        ->call('authenticate')
        ->assertHasErrors([
            'data.login' => trans('auth.account_suspended_contact', ['email' => 'support@example.com']),
        ]);

    livewire(Login::class)
        ->fillForm([
            'login' => $user->email,
            'password' => 'wrong-password',
        ])
        ->call('authenticate')
        ->assertHasErrors(['data.login']);
});
