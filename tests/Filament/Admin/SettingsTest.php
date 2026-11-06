<?php

use App\Events\ActivityLogged;
use App\Extensions\Captcha\CaptchaService;
use App\Extensions\OAuth\OAuthService;
use App\Filament\Admin\Pages\Settings;
use App\Helpers\ProcessEnv;
use App\Models\Role;
use Filament\Actions\Testing\TestAction;
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

it('reads oauth and captcha keys from config', function () {
    config()->set('extensions.OAUTH_GITHUB_ENABLED', true);
    config()->set('extensions.CAPTCHA_TURNSTILE_ENABLED', true);

    expect(app(OAuthService::class)->get('github')->isEnabled())->toBeTrue()
        ->and(app(CaptchaService::class)->get('turnstile')->isEnabled())->toBeTrue();
});

it('collects oauth and captcha variables into the extensions config', function () {
    $_ENV['OAUTH_EXAMPLE_ENABLED'] = 'true';
    $_ENV['CAPTCHA_EXAMPLE_SITE_KEY'] = 'site-key';
    $_ENV['UNRELATED_KEY'] = 'nope';

    try {
        $config = require config_path('extensions.php');
    } finally {
        unset($_ENV['OAUTH_EXAMPLE_ENABLED'], $_ENV['CAPTCHA_EXAMPLE_SITE_KEY'], $_ENV['UNRELATED_KEY']);
    }

    expect($config)->toMatchArray(['OAUTH_EXAMPLE_ENABLED' => true, 'CAPTCHA_EXAMPLE_SITE_KEY' => 'site-key'])
        ->not->toHaveKey('UNRELATED_KEY');
});

it('locks fields set in the server environment and never writes them', function () {
    ProcessEnv::capture(['MAIL_HOST' => 'smtp.container.test', 'TRUSTED_PROXIES' => '10.0.0.1', ProcessEnv::FROM_FILE => 'TRUSTED_PROXIES']);

    $path = sys_get_temp_dir().'/pelican-settings-lock-'.getmypid();
    @mkdir($path);
    file_put_contents("$path/.env", '');
    app()->useEnvironmentPath($path);

    try {
        livewire(Settings::class)
            ->assertFormFieldDisabled('MAIL_HOST')
            ->assertFormFieldEnabled('TRUSTED_PROXIES')
            ->fillForm([
                'APP_NAME' => 'Locked Panel',
                'FILAMENT_AVATAR_PROVIDER' => 'gravatar',
                'MAIL_MAILER' => 'log',
                'GUZZLE_CONNECT_TIMEOUT' => 5,
            ])
            ->call('save')
            ->assertHasNoErrors();

        expect(file_get_contents("$path/.env"))->toContain('APP_NAME="Locked Panel"')
            ->not->toContain('MAIL_HOST=');
    } finally {
        @unlink("$path/.env");
        @rmdir($path);
    }
});

it('is read only when the environment file is not writable', function () {
    $path = sys_get_temp_dir().'/pelican-settings-ro-'.getmypid();
    @mkdir($path);
    file_put_contents("$path/.env", "APP_NAME=Before\n");
    chmod("$path/.env", 0444);
    app()->useEnvironmentPath($path);

    try {
        livewire(Settings::class)
            ->assertSee(trans('admin/setting.env_read_only'))
            ->assertFormFieldDisabled('APP_NAME')
            ->call('save')
            ->assertNotified(trans('admin/setting.save_failed'));

        expect(file_get_contents("$path/.env"))->toBe("APP_NAME=Before\n");
    } finally {
        chmod("$path/.env", 0644);
        @unlink("$path/.env");
        @rmdir($path);
    }
});

it('logs only the changed settings with secrets masked', function () {
    $path = sys_get_temp_dir().'/pelican-settings-audit-'.getmypid();
    @mkdir($path);
    file_put_contents("$path/.env", '');
    app()->useEnvironmentPath($path);
    Event::fake(ActivityLogged::class);

    try {
        livewire(Settings::class)
            ->fillForm([
                'APP_NAME' => 'Audited Panel',
                'FILAMENT_AVATAR_PROVIDER' => 'gravatar',
                'MAIL_MAILER' => 'smtp',
                'MAIL_HOST' => 'smtp.audit.test',
                'MAIL_PORT' => 2525,
                'MAIL_SCHEME' => 'smtp',
                'MAIL_PASSWORD' => 'supersecretpw',
                'GUZZLE_CONNECT_TIMEOUT' => 5,
            ])
            ->call('save')
            ->assertHasNoErrors();
    } finally {
        @unlink("$path/.env");
        @rmdir($path);
    }

    Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $event) {
        if (!$event->is('audit:settings.update')) {
            return false;
        }

        $changes = $event->model->properties['changes'];

        return $changes['APP_NAME'] === ['old' => config('app.name'), 'new' => 'Audited Panel']
            && $changes['MAIL_PASSWORD']['new'] === '********'
            && $event->model->properties['count'] === count($changes)
            && !array_key_exists('APP_DEBUG', $changes)
            && !array_key_exists('PANEL_USE_BINARY_PREFIX', $changes)
            && !array_key_exists('TRUSTED_PROXIES', $changes)
            && !array_key_exists('APP_2FA_REQUIRED', $changes)
            && !str_contains(json_encode($changes), 'supersecretpw');
    });
});

it('logs text settings that only look like booleans', function () {
    $path = sys_get_temp_dir().'/pelican-settings-text-'.getmypid();
    @mkdir($path);
    file_put_contents("$path/.env", '');
    app()->useEnvironmentPath($path);
    config()->set('app.name', '1');
    config()->set('panel.filament.avatar-provider', 'gravatar');
    config()->set('mail.default', 'log');
    Event::fake(ActivityLogged::class);

    try {
        livewire(Settings::class)->fillForm(['APP_NAME' => 'true', 'GUZZLE_CONNECT_TIMEOUT' => 5])->call('save')->assertHasNoErrors();
    } finally {
        @unlink("$path/.env");
        @rmdir($path);
    }

    Event::assertDispatched(ActivityLogged::class, fn (ActivityLogged $event) => $event->is('audit:settings.update')
        && $event->model->properties['changes']['APP_NAME'] === ['old' => '1', 'new' => 'true']);
});

it('does not log a save that changes nothing', function () {
    $path = sys_get_temp_dir().'/pelican-settings-noop-'.getmypid();
    @mkdir($path);
    file_put_contents("$path/.env", '');
    app()->useEnvironmentPath($path);
    config()->set('panel.filament.avatar-provider', 'gravatar');
    config()->set('mail.default', 'log');
    Event::fake(ActivityLogged::class);

    try {
        livewire(Settings::class)->call('save')->assertHasNoErrors();
    } finally {
        @unlink("$path/.env");
        @rmdir($path);
    }

    Event::assertNotDispatched(ActivityLogged::class, fn (ActivityLogged $event) => $event->is('audit:settings.update'));
});

it('locks the captcha toggle when the server environment enables it', function () {
    ProcessEnv::capture(['CAPTCHA_TURNSTILE_ENABLED' => 'true']);
    config()->set('extensions.CAPTCHA_TURNSTILE_ENABLED', true);

    livewire(Settings::class)
        ->assertActionDisabled(TestAction::make('disable_captcha_TURNSTILE')->schemaComponent('captcha_TURNSTILE_actions'));
});
