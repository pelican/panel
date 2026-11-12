<?php

use App\Filament\Admin\Widgets\UpdateProgressWidget;
use App\Filament\Admin\Widgets\UpdateWidget;
use App\Models\Role;
use App\Models\User;
use App\Services\Helpers\PanelUpdateService;
use App\Services\Helpers\PluginService;
use App\Services\Helpers\SoftwareVersionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

$passingPreflight = ['errors' => [], 'warnings' => [], 'plugins' => [], 'needs_backup_confirmation' => false];

/**
 * A real service whose preflight returns the given result, the real checks depend on the machine running the tests.
 *
 * @param  array<string, mixed>  $preflight
 */
function updateServiceWithPreflight(array $preflight): PanelUpdateService
{
    $service = Mockery::mock(PanelUpdateService::class, [app(SoftwareVersionService::class), app(PluginService::class)])->makePartial();
    $service->shouldReceive('preflight')->andReturn($preflight);
    app()->instance(PanelUpdateService::class, $service);

    return $service;
}

beforeEach(function () {
    cache()->flush();
    config()->set('app.version', '1.0.0');
});

afterEach(function () {
    app(PanelUpdateService::class)->dismissStatus();
});

it('refuses to update canary builds', function () {
    config()->set('app.version', 'canary');

    expect(app(PanelUpdateService::class)->preflight('1.1.0')['errors'])
        ->toContain(trans('admin/update.preflight.canary'));
})->skip(fn () => is_running_in_docker(), 'Docker installs are refused before the version is checked');

it('refuses a target that is not newer', function (string $target) {
    expect(app(PanelUpdateService::class)->preflight($target)['errors'])
        ->toContain(trans('admin/update.preflight.not_newer', ['current' => '1.0.0', 'target' => $target]));
})->with(['1.0.0', '0.9.0'])->skip(fn () => is_running_in_docker(), 'Docker installs are refused before the version is checked');

it('only checks when asked to', function () use ($passingPreflight) {
    updateServiceWithPreflight($passingPreflight);
    Process::fake();

    $this->artisan('p:update', ['version' => '1.1.0', '--check' => true])
        ->expectsOutputToContain('Ready to update to 1.1.0')
        ->assertSuccessful();

    Process::assertNothingRan();
});

it('fails the command when preflight fails', function () {
    updateServiceWithPreflight([
        'errors' => ['nope'], 'warnings' => [], 'plugins' => [], 'needs_backup_confirmation' => false,
    ]);

    $this->artisan('p:update', ['version' => '1.1.0', '--no-interaction' => true])
        ->expectsOutputToContain('nope')
        ->assertFailed();
});

it('aborts before touching the install when the checksum does not match', function () use ($passingPreflight) {
    $service = updateServiceWithPreflight($passingPreflight);
    Process::fake();
    Http::fake([
        'api.github.com/repos/pelican/panel/releases/tags/v1.1.0' => Http::response(['assets' => [
            ['name' => 'panel.tar.gz', 'browser_download_url' => 'https://github.com/pelican/panel/releases/download/v1.1.0/panel.tar.gz'],
            ['name' => 'checksum.txt', 'browser_download_url' => 'https://github.com/pelican/panel/releases/download/v1.1.0/checksum.txt'],
        ]]),
        'github.com/pelican/panel/releases/download/v1.1.0/checksum.txt' => Http::response(str_repeat('a', 64) . '  panel.tar.gz'),
        'github.com/pelican/panel/releases/download/v1.1.0/panel.tar.gz' => Http::response('not the real archive'),
    ]);

    $succeeded = $service->run('1.1.0', 'secret', false, false, null, fn () => null);

    expect($succeeded)->toBeFalse()
        ->and($service->status())
        ->state->toBe(PanelUpdateService::STATE_FAILED)
        ->message->toContain('checksum');

    Process::assertNothingRan();
});

it('requires confirmation for plugins that would become incompatible', function () {
    updateServiceWithPreflight([
        'errors' => [], 'warnings' => [], 'plugins' => ['Example'], 'needs_backup_confirmation' => false,
    ]);

    $user = User::factory()->create();
    $user->syncRoles(Role::getRootAdmin());

    expect(fn () => app(PanelUpdateService::class)->launch($user, '1.1.0'))
        ->toThrow(Exception::class, 'Example');
});

it('only shows the update status to root admins', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('update.status'))
        ->assertForbidden();

    $admin = User::factory()->create();
    $admin->syncRoles(Role::getRootAdmin());

    $this->actingAs($admin)
        ->getJson(route('update.status'))
        ->assertOk()
        ->assertJson(['state' => null]);
});

it('swaps the release in and rolls it back without losing user data', function () {
    $original = base_path();
    $root = sys_get_temp_dir() . '/panel-update-test-' . Str::random(8);

    $put = function (string $path, string $contents) {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    };

    // The live install, with user data mixed into release directories.
    $put("$root/app/Old.php", 'old');
    $put("$root/public/index.php", 'old index');
    $put("$root/public/custom-logo.png", 'user file');
    $put("$root/database/migrations/old.php", 'old migration');
    $put("$root/database/database.sqlite", 'user database');
    $put("$root/storage/app/user-upload.txt", 'upload');
    $put("$root/plugins/example/plugin.json", '{}');
    $put("$root/.env", 'APP_KEY=secret');
    $put("$root/node_modules/left-alone.js", 'not in the release');

    // The staged release.
    $release = "$root/staging/release";
    $put("$release/app/New.php", 'new');
    $put("$release/public/index.php", 'new index');
    $put("$release/database/migrations/new.php", 'new migration');
    $put("$release/vendor/autoload.php", 'new vendor');
    $put("$release/storage/.gitignore", '*');
    $put("$release/plugins/.gitignore", '*');

    app()->setBasePath($root);
    Process::fake();

    try {
        $service = app(PanelUpdateService::class);
        $call = fn (string $method, mixed ...$arguments) => (new ReflectionMethod($service, $method))->invokeArgs($service, $arguments);

        $swapped = [];
        $carried = [];
        $codeBackup = "$root/backup/code";
        (new ReflectionMethod($service, 'swap'))->invokeArgs($service, [$release, $codeBackup, &$swapped, &$carried]);

        expect(File::get("$root/app/New.php"))->toBe('new')
            ->and(File::exists("$root/app/Old.php"))->toBeFalse()
            ->and(File::get("$root/public/index.php"))->toBe('new index')
            ->and(File::get("$root/public/custom-logo.png"))->toBe('user file')
            ->and(File::get("$root/database/database.sqlite"))->toBe('user database')
            ->and(File::exists("$root/database/migrations/old.php"))->toBeFalse()
            ->and(File::get("$root/vendor/autoload.php"))->toBe('new vendor')
            ->and(File::get("$root/storage/app/user-upload.txt"))->toBe('upload')
            ->and(File::exists("$root/plugins/example/plugin.json"))->toBeTrue()
            ->and(File::get("$root/.env"))->toBe('APP_KEY=secret')
            ->and(File::exists("$root/node_modules/left-alone.js"))->toBeTrue();

        expect($call('rollback', $swapped, $carried, $release, $codeBackup, null, false))->toBeTrue();

        expect(File::get("$root/app/Old.php"))->toBe('old')
            ->and(File::exists("$root/app/New.php"))->toBeFalse()
            ->and(File::get("$root/public/index.php"))->toBe('old index')
            ->and(File::get("$root/public/custom-logo.png"))->toBe('user file')
            ->and(File::get("$root/database/database.sqlite"))->toBe('user database')
            ->and(File::exists("$root/database/migrations/old.php"))->toBeTrue()
            ->and(File::exists("$root/vendor"))->toBeFalse()
            ->and(File::get("$root/storage/app/user-upload.txt"))->toBe('upload')
            ->and(File::get("$root/.env"))->toBe('APP_KEY=secret');

        Process::assertRan(fn ($process) => in_array('up', (array) $process->command, true));
    } finally {
        app()->setBasePath($original);
        File::deleteDirectory($root);
    }
});

it('keeps the panel down when migrations ran and there is no database backup', function () {
    $service = app(PanelUpdateService::class);
    Process::fake();

    $restored = (new ReflectionMethod($service, 'rollback'))->invoke($service, [], [], '/nonexistent', sys_get_temp_dir() . '/panel-update-' . Str::random(8), null, true);

    expect($restored)->toBeFalse();
    Process::assertDidntRun(fn ($process) => in_array('up', (array) $process->command, true));
});

it('offers the update on the dashboard to root admins', function () {
    Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v1.1.0', 'body' => 'Release notes'])]);

    $admin = User::factory()->create();
    $admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($admin);

    Livewire::test(UpdateWidget::class)
        ->assertOk()
        ->assertSee(trans('admin/update.button'));
})->skip(fn () => is_running_in_docker(), 'Docker installs update by recreating the container');

it('shows the progress of a running update', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($admin);

    $status = storage_path('app/private/update/status.json');
    File::ensureDirectoryExists(dirname($status));
    File::put($status, json_encode(['state' => 'running', 'step' => 'download', 'from' => '1.0.0', 'to' => '1.1.0', 'log' => ['==> Download']]));

    expect(UpdateProgressWidget::canView())->toBeTrue();

    Livewire::test(UpdateProgressWidget::class)
        ->assertOk()
        ->assertSee(trans('admin/update.progress.heading'))
        ->assertSee(trans('admin/update.progress.steps.download'));
});

it('refuses to start an update for admins who are not root admins', function () use ($passingPreflight) {
    updateServiceWithPreflight($passingPreflight);
    Process::fake();

    $role = Role::create(['name' => 'Limited admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->syncRoles($role);

    expect(fn () => app(PanelUpdateService::class)->launch($admin, '1.1.0'))
        ->toThrow(AuthorizationException::class);

    Process::assertNothingRan();
});

it('hides the update from admins who are not root admins', function () {
    Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v1.1.0', 'body' => 'Release notes'])]);

    $admin = User::factory()->create();
    $this->actingAs($admin);

    Livewire::test(UpdateWidget::class)
        ->assertOk()
        ->assertDontSee(trans('admin/update.button'));

    expect(UpdateProgressWidget::canView())->toBeFalse();

    expect(fn () => (new UpdateProgressWidget())->dismiss(app(PanelUpdateService::class)))
        ->toThrow(HttpException::class);
});
