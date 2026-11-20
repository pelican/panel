<?php

use App\Filament\Admin\Pages\Settings;
use App\Models\Role;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

// The Panel-wide level the node auto-deploy command gives new nodes (pelican/wings contract v2, L).

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    [$this->admin] = generateTestAccount();
    $this->admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($this->admin);

    // A scratch environment file, so the real .env is untouched
    $this->envPath = sys_get_temp_dir() . '/pelican-wings-access-test-' . getmypid();
    @mkdir($this->envPath);
    file_put_contents("$this->envPath/.env", '');
    app()->useEnvironmentPath($this->envPath);
});

afterEach(function () {
    Filament::setCurrentPanel(null);
    @unlink("$this->envPath/.env");
    @rmdir($this->envPath);
});

/** @return array<string, mixed> */
function wingsAccessSettingsForm(mixed $level): array
{
    return [
        'PANEL_WINGS_PLUGIN_ACCESS' => $level,
        // The testing env values for these fail the form's own validation
        'FILAMENT_AVATAR_PROVIDER' => 'gravatar',
        'MAIL_MAILER' => 'log',
        'GUZZLE_CONNECT_TIMEOUT' => 5,
    ];
}

it('is not set by default', function () {
    config()->set('panel.plugin.wings_access');

    livewire(Settings::class)->assertSchemaStateSet(['PANEL_WINGS_PLUGIN_ACCESS' => null]);
});

it('saves the level for new nodes', function () {
    livewire(Settings::class)
        ->fillForm(wingsAccessSettingsForm('signed'))
        ->call('save')
        ->assertHasNoErrors();

    expect(file_get_contents("$this->envPath/.env"))->toContain('PANEL_WINGS_PLUGIN_ACCESS=signed');
});

it('refuses a level wings does not know', function () {
    livewire(Settings::class)
        ->fillForm(wingsAccessSettingsForm('root'))
        ->call('save')
        // The page reports a failed save instead of field errors
        ->assertNotified(trans('admin/setting.save_failed'));

    expect(file_get_contents("$this->envPath/.env"))->not->toContain('PANEL_WINGS_PLUGIN_ACCESS');
});
