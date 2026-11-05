<?php

use App\Helpers\ProcessEnv;
use App\Models\ApiKey;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\User;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->envPath = sys_get_temp_dir().'/pelican-process-env-'.getmypid();
    File::ensureDirectoryExists($this->envPath);
    File::put("$this->envPath/.env", "APP_NAME=Pelican\n");
    app()->useEnvironmentPath($this->envPath);
});

afterEach(fn () => File::deleteDirectory($this->envPath));

it('treats keys the docker entrypoint copied out of .env as file values', function () {
    ProcessEnv::capture(['MAIL_HOST' => 'smtp.test', 'TRUSTED_PROXIES' => '10.0.0.1', 'DB_HOST' => 'db', ProcessEnv::FROM_FILE => ' TRUSTED_PROXIES DB_HOST']);

    expect(ProcessEnv::has('MAIL_HOST'))->toBeTrue()
        ->and(ProcessEnv::has('TRUSTED_PROXIES'))->toBeFalse()
        ->and(ProcessEnv::has('DB_HOST'))->toBeFalse()
        ->and(ProcessEnv::has(ProcessEnv::FROM_FILE))->toBeFalse();
});

it('drops the entrypoint copies so the current .env value wins', function () {
    putenv('W11_FROM_FILE=stale');
    putenv(ProcessEnv::FROM_FILE.'=W11_FROM_FILE');
    $_SERVER['W11_FROM_FILE'] = 'stale';

    try {
        ProcessEnv::capture();

        expect(getenv('W11_FROM_FILE'))->toBeFalse()
            ->and($_SERVER)->not->toHaveKey('W11_FROM_FILE')
            ->and(ProcessEnv::has('W11_FROM_FILE'))->toBeFalse();
    } finally {
        putenv(ProcessEnv::FROM_FILE);
        ProcessEnv::capture([]);
    }
});

it('refuses hub credentials that the server environment already sets', function () {
    ProcessEnv::capture(['PANEL_PLUGIN_HUB_API_KEY' => 'pnl_fromcontainer']);

    $user = User::factory()->create();
    $user->syncRoles(Role::getRootAdmin());
    $key = ApiKey::factory()->create(['user_id' => $user->id, 'key_type' => ApiKey::TYPE_APPLICATION, 'permissions' => [Plugin::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE]]);

    $this->withHeader('Authorization', 'Bearer '.$key->identifier.$key->token)
        ->putJson('/api/application/plugins/hub', ['hub_url' => 'https://hub.pelican.dev', 'api_key' => 'pnl_abc123XYZ'])
        ->assertStatus(409);

    expect(File::get("$this->envPath/.env"))->toBe("APP_NAME=Pelican\n");
});

it('skips mail settings that the server environment already sets', function () {
    ProcessEnv::capture(['MAIL_FROM_NAME' => 'Container']);

    $exitCode = Artisan::call('p:environment:mail', ['--driver' => 'log', '--email' => 'panel@example.test', '--from' => 'Panel', '--no-interaction' => true]);

    expect($exitCode)->toBe(0)
        ->and(File::get("$this->envPath/.env"))->toContain('MAIL_FROM_ADDRESS="panel@example.test"')
        ->not->toContain('MAIL_FROM_NAME');
});
