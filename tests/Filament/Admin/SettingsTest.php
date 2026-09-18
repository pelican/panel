<?php

use App\Events\ActivityLogged;
use App\Filament\Admin\Pages\Settings;
use App\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);
});
afterEach(fn () => Filament::setCurrentPanel(null));

it('renders for a root admin', function () {
    livewire(Settings::class)->assertSuccessful();
});

it('is forbidden for a user without permission', function () {
    [$user] = generateTestAccount();

    $this->actingAs($user);
    livewire(Settings::class)->assertForbidden();
});

it('logs a settings:update event with a redacted diff', function () {
    $path = sys_get_temp_dir().'/pelican-settings-test-'.getmypid();
    @mkdir($path);
    file_put_contents("$path/.env", '');
    app()->useEnvironmentPath($path);

    try {
        livewire(Settings::class)
            ->fillForm([
                'APP_NAME' => 'Audited Panel',
                'FILAMENT_AVATAR_PROVIDER' => 'gravatar',
                // smtp keeps the MAIL_PASSWORD field visible so it lands in the diff
                'MAIL_MAILER' => 'smtp',
                'MAIL_HOST' => 'localhost',
                'MAIL_PORT' => 2525,
                'MAIL_SCHEME' => 'smtp',
                'MAIL_PASSWORD' => 'supersecretpw',
                'GUZZLE_CONNECT_TIMEOUT' => 5,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertActivityLogged('settings:update');
        $this->assertActivityActor('settings:update', $this->admin);
        Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $e) {
            if (!$e->is('settings:update')) {
                return false;
            }
            $changes = $e->model->properties['changes'];

            return $changes['APP_NAME']['new'] === 'Audited Panel'
                && $changes['MAIL_PASSWORD']['new'] === '********'
                && !str_contains(json_encode($e->model->properties), 'supersecretpw');
        });
    } finally {
        @unlink("$path/.env");
        @rmdir($path);
    }
});

it('persists saved settings to the environment file', function () {
    // Point the environment file at a scratch copy so the real .env is untouched.
    $path = sys_get_temp_dir().'/pelican-settings-test-'.getmypid();
    @mkdir($path);
    file_put_contents("$path/.env", '');
    app()->useEnvironmentPath($path);

    try {
        livewire(Settings::class)
            ->fillForm([
                'APP_NAME' => 'Renamed Panel',
                // The testing env values for these fail the form's own validation
                'FILAMENT_AVATAR_PROVIDER' => 'gravatar',
                'MAIL_MAILER' => 'log',
                'GUZZLE_CONNECT_TIMEOUT' => 5,
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        expect(file_get_contents("$path/.env"))->toContain('APP_NAME="Renamed Panel"');
    } finally {
        @unlink("$path/.env");
        @rmdir($path);
    }
});
