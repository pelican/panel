<?php

use App\Extensions\Captcha\CaptchaService;
use App\Extensions\OAuth\OAuthService;
use App\Filament\Admin\Pages\Settings;
use App\Models\Role;
use Filament\Facades\Filament;

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
