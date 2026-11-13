<?php

use App\Filament\Admin\Widgets\UpdateProgressWidget;
use App\Filament\Admin\Widgets\UpdateWidget;
use App\Models\Role;
use App\Models\User;
use App\Services\Helpers\PanelUpdateService;
use App\Services\Helpers\PluginService;
use App\Services\Helpers\SoftwareVersionService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
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

    // The updater keeps its status, locks and backups in storage, keep the install's own out of reach. It stays on
    // the install's filesystem, otherwise workPath() would fall back to a directory in the install itself.
    $this->originalStoragePath = storage_path();
    $this->updateStoragePath = storage_path('framework/testing/panel-update-' . Str::random(8));
    File::ensureDirectoryExists($this->updateStoragePath);
    app()->useStoragePath($this->updateStoragePath);
});

afterEach(function () {
    app()->useStoragePath($this->originalStoragePath);
    File::deleteDirectory($this->updateStoragePath);
});

it('refuses to update canary builds', function () {
    config()->set('app.version', 'canary');

    expect(app(PanelUpdateService::class)->preflight('1.1.0')['errors'])
        ->toContain(trans('admin/update.preflight.canary'));
})->skip(fn () => PHP_OS_FAMILY === 'Windows' || is_running_in_docker(), 'Windows and Docker installs are refused before the version is checked');

it('refuses a target that is not newer', function (string $target) {
    expect(app(PanelUpdateService::class)->preflight($target)['errors'])
        ->toContain(trans('admin/update.preflight.not_newer', ['current' => '1.0.0', 'target' => $target]));
})->with(['1.0.0', '0.9.0'])->skip(fn () => PHP_OS_FAMILY === 'Windows' || is_running_in_docker(), 'Windows and Docker installs are refused before the version is checked');

it('refuses to update on Windows', function () {
    expect(app(PanelUpdateService::class)->preflight('1.1.0')['errors'])
        ->toContain(trans('admin/update.preflight.windows'));
})->skip(PHP_OS_FAMILY !== 'Windows', 'Only applies to Windows');

it('treats a recently queued update as running until its process takes the lock', function (int $minutesAgo, bool $busy) {
    $status = storage_path('app/private/update/status.json');
    File::ensureDirectoryExists(dirname($status));
    File::put($status, json_encode(['state' => PanelUpdateService::STATE_QUEUED, 'updated_at' => now()->subMinutes($minutesAgo)->toAtomString()]));

    expect(app(PanelUpdateService::class)->isBusy())->toBe($busy);
})->with([
    'just launched' => [0, true],
    'process never started' => [5, false],
]);

it('refuses a second launch while another one is being started', function () use ($passingPreflight) {
    updateServiceWithPreflight($passingPreflight);
    Process::fake();

    $admin = User::factory()->create();
    $admin->syncRoles(Role::getRootAdmin());

    $path = storage_path('app/private/update/launch.lock');
    File::ensureDirectoryExists(dirname($path));
    $lock = fopen($path, 'c');
    flock($lock, LOCK_EX);

    try {
        expect(fn () => app(PanelUpdateService::class)->launch($admin, '1.1.0'))
            ->toThrow(Exception::class, trans('admin/update.preflight.running'));

        Process::assertNothingRan();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

it('does not let the command overwrite an update a dashboard launch is queuing', function () use ($passingPreflight) {
    updateServiceWithPreflight($passingPreflight);
    Process::fake();

    $path = storage_path('app/private/update/launch.lock');
    File::ensureDirectoryExists(dirname($path));
    $lock = fopen($path, 'c');
    flock($lock, LOCK_EX);

    try {
        $this->artisan('p:update', ['version' => '1.1.0', '--no-interaction' => true])
            ->expectsOutputToContain(trans('admin/update.preflight.running'))
            ->assertFailed();

        expect(File::exists(storage_path('app/private/update/request.json')))->toBeFalse()
            ->and(File::exists(storage_path('app/private/update/status.json')))->toBeFalse();
        Process::assertNothingRan();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

it('refuses a new update while the last one is left unresolved by a failed rollback', function () {
    $status = storage_path('app/private/update/status.json');
    File::ensureDirectoryExists(dirname($status));
    File::put($status, json_encode(['state' => PanelUpdateService::STATE_ROLLBACK_FAILED, 'updated_at' => now()->toIso8601String()]));

    expect(implode("\n", app(PanelUpdateService::class)->preflight('1.1.0')['errors']))
        ->toContain('could not be rolled back');

    File::delete($status);

    expect(implode("\n", app(PanelUpdateService::class)->preflight('1.1.0')['errors']))
        ->not->toContain('could not be rolled back');
});

it('reports an update whose process died instead of showing it as running forever', function (string $state, ?string $step, int $minutesAgo, string $expected) {
    $status = storage_path('app/private/update/status.json');
    File::ensureDirectoryExists(dirname($status));
    File::put($status, json_encode(['state' => $state, 'step' => $step, 'updated_at' => now()->subMinutes($minutesAgo)->toAtomString()]));

    $service = app(PanelUpdateService::class);

    expect($service->status()['state'])->toBe($expected);
    if ($expected !== $state) {
        expect($service->status()['message'])->toBe(trans('admin/update.progress.stopped'));
    }
})->with([
    'queued, still starting' => [PanelUpdateService::STATE_QUEUED, null, 0, PanelUpdateService::STATE_QUEUED],
    'queued, never started' => [PanelUpdateService::STATE_QUEUED, null, 5, PanelUpdateService::STATE_FAILED],
    'died before touching the install' => [PanelUpdateService::STATE_RUNNING, 'dependencies', 0, PanelUpdateService::STATE_FAILED],
    'died after maintenance mode' => [PanelUpdateService::STATE_RUNNING, 'swap', 0, PanelUpdateService::STATE_ROLLBACK_FAILED],
]);

it('keeps showing a running update while its process holds the lock', function () {
    $status = storage_path('app/private/update/status.json');
    File::ensureDirectoryExists(dirname($status));
    File::put($status, json_encode(['state' => PanelUpdateService::STATE_RUNNING, 'step' => 'swap', 'updated_at' => now()->subHour()->toAtomString()]));

    $lock = fopen(storage_path('app/private/update/update.lock'), 'c');
    flock($lock, LOCK_EX);

    try {
        expect(app(PanelUpdateService::class)->status()['state'])->toBe(PanelUpdateService::STATE_RUNNING);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

it('reports running queue jobs to the update script', function () {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database', ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]);

    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 1, 'reserved_at' => time() - 10, 'available_at' => time(), 'created_at' => time()]);
    $this->artisan('p:update', ['--running-jobs' => true])->expectsOutput('1')->assertSuccessful();

    // A reservation older than retry_after belongs to a worker that died.
    DB::table('jobs')->update(['reserved_at' => time() - 600]);
    $this->artisan('p:update', ['--running-jobs' => true])->expectsOutput('0')->assertSuccessful();

    // An unknown queue state must not count as an idle queue.
    config()->set('queue.connections.database.table', 'missing_jobs_table');
    $this->artisan('p:update', ['--running-jobs' => true])->assertFailed();
});

it('resolves DB_URL so backups and restores reach the panel database', function () {
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql.url', 'mysql://panel:secret@db.internal:3307/pelican');
    config()->set('database.connections.mysql.host', '127.0.0.1');

    $service = app(PanelUpdateService::class);

    expect((new ReflectionMethod($service, 'databaseConfig'))->invoke($service))
        ->host->toBe('db.internal')
        ->port->toBe(3307)
        ->database->toBe('pelican')
        ->username->toBe('panel');
});

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

    putenv(PanelUpdateService::LOCKED_ENV . '=1');

    try {
        $succeeded = $service->prepare(['target' => '1.1.0', 'secret' => 'secret', 'user_id' => null, 'force_plugins' => false, 'skip_db_backup' => false], fn () => null);
    } finally {
        putenv(PanelUpdateService::LOCKED_ENV);
    }

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

    // A running update holds the lock, without it the status is reported as a process that died.
    $lock = fopen(storage_path('app/private/update/update.lock'), 'c');
    flock($lock, LOCK_EX);

    try {
        expect(UpdateProgressWidget::canView())->toBeTrue();

        Livewire::test(UpdateProgressWidget::class)
            ->assertOk()
            ->assertSee(trans('admin/update.progress.heading'))
            ->assertSee(trans('admin/update.progress.steps.download'));
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
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

it('opens the update modal and starts the update', function () use ($passingPreflight) {
    Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v1.1.0', 'body' => 'Release notes'])]);

    $service = updateServiceWithPreflight($passingPreflight);
    $service->shouldReceive('launch')->once()->andReturn('secret');

    $admin = User::factory()->create();
    $admin->syncRoles(Role::getRootAdmin());
    $this->actingAs($admin);

    Livewire::test(UpdateWidget::class)
        ->callAction(TestAction::make('update_now')->schemaComponent('update-available', schema: 'form'))
        ->assertHasNoActionErrors()
        ->assertRedirect();
})->skip(fn () => is_running_in_docker(), 'Docker installs update by recreating the container');

it('only prepares a release while the update lock is held', function () {
    $service = app(PanelUpdateService::class);
    Http::fake();

    expect($service->prepare(['target' => '1.1.0', 'secret' => 'secret', 'user_id' => null, 'force_plugins' => false, 'skip_db_backup' => false], fn () => null))
        ->toBeFalse();

    Http::assertNothingSent();
});

it('hands the update to the script of the release it installs, under one lock', function () {
    $service = app(PanelUpdateService::class);
    $command = $service->updateCommand();

    expect($command[1])->toBe('-n')
        ->and($command[2])->toEndWith('update.lock')
        ->and($command[5])->toContain('p:update')->toContain('--from-request')
        ->and($command[5])->toContain('&& exec ')->toContain('update.sh')
        ->and($service->updateEnv())->toHaveKey(PanelUpdateService::LOCKED_ENV, '1');
});
