<?php

namespace App\Tests\Integration\Api\Client\Server;

use App\Enums\RolePermissionModels;
use App\Enums\SubuserPermission;
use App\Models\Allocation;
use App\Models\ApiKey;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Node;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\Subuser;
use App\Models\Task;
use App\Models\User;
use App\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;

/**
 * Runs every server scoped client API endpoint against every kind of caller and checks
 * the caller is let through, refused (403) or kept unaware of the server (404) as expected.
 *
 * "allow" only means authorization passed: the endpoint may still fail afterwards (validation,
 * daemon or database host errors), so any status other than 401/403/404 counts.
 */
class PermissionMatrixTest extends ClientApiIntegrationTestCase
{
    private Server $server;

    private Allocation $extraAllocation;

    private Database $database;

    private Schedule $schedule;

    private Task $task;

    private Backup $backup;

    private User $targetSubuser;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response([], 200)]);
    }

    #[DataProvider('endpointProvider')]
    public function test_endpoint_authorization(string $method, string $path, ?string $permission, array $extra, array $body): void
    {
        $mismatches = [];

        foreach (self::ACTORS as $actor) {
            $this->resetAuthentication();
            $this->buildFixtures();

            $expected = $this->expectationFor($actor, $path, $permission);
            $this->authenticateAs($actor, $permission, $extra);

            $url = "/api/client/servers/{$this->server->uuid}" . $this->resolvePath($path);
            $response = $this->json($method, $url, $body);
            $status = $response->getStatusCode();

            $this->record($actor, $method, $path, $permission, $expected, $status);

            $ok = match ($expected) {
                'allow' => !in_array($status, [401, 403, 404]),
                'forbid' => $status === 403,
                'hidden' => $status === 404,
            };

            if (!$ok) {
                $mismatches[] = "$actor: expected $expected, got $status";
            }
        }

        $this->assertEmpty($mismatches, "$method $path
" . implode('
', $mismatches));
    }

    private const ACTORS = [
        'owner',
        'subuser_with_permission',
        'subuser_without_permission',
        'stranger',
        'root_admin',
        'admin_update_server',
        'admin_view_server',
        'admin_update_server_other_node',
        'application_key',
    ];

    public static function endpointProvider(): array
    {
        $cases = [];
        foreach (self::endpoints() as [$method, $path, $permission, $extra, $body]) {
            $cases["$method $path"] = [$method, $path, $permission?->value, array_map(fn ($p) => $p->value, $extra), $body];
        }

        return $cases;
    }

    private function resetAuthentication(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
    }

    /**
     * @return array<array{string, string, ?SubuserPermission, SubuserPermission[], array}>
     */
    private static function endpoints(): array
    {
        return [
            ['GET', '/', null, [], []],
            ['GET', '/websocket', SubuserPermission::WebsocketConnect, [], []],
            ['GET', '/resources', null, [], []],
            ['GET', '/activity', SubuserPermission::ActivityRead, [], []],
            ['POST', '/command', SubuserPermission::ControlConsole, [], ['command' => 'say hi']],
            ['POST', '/power', SubuserPermission::ControlStart, [], ['signal' => 'start']],
            ['POST', '/power#stop', SubuserPermission::ControlStop, [], ['signal' => 'stop']],
            ['POST', '/power#restart', SubuserPermission::ControlRestart, [], ['signal' => 'restart']],
            ['POST', '/power#kill', SubuserPermission::ControlStop, [], ['signal' => 'kill']],

            ['GET', '/databases', SubuserPermission::DatabaseRead, [], []],
            ['POST', '/databases', SubuserPermission::DatabaseCreate, [], ['database' => 'perm_test', 'remote' => '%']],
            ['POST', '/databases/{database}/rotate-password', SubuserPermission::DatabaseUpdate, [], []],
            ['DELETE', '/databases/{database}', SubuserPermission::DatabaseDelete, [], []],

            ['GET', '/files/list?directory=/', SubuserPermission::FileRead, [], []],
            ['GET', '/files/contents?file=/a.txt', SubuserPermission::FileReadContent, [], []],
            ['GET', '/files/download?file=/a.txt', SubuserPermission::FileReadContent, [], []],
            ['PUT', '/files/rename', SubuserPermission::FileUpdate, [], ['root' => '/', 'files' => [['from' => 'a.txt', 'to' => 'b.txt']]]],
            ['POST', '/files/copy', SubuserPermission::FileCreate, [], ['location' => '/a.txt']],
            ['POST', '/files/write?file=/a.txt', SubuserPermission::FileCreate, [], []],
            ['POST', '/files/compress', SubuserPermission::FileArchive, [], ['root' => '/', 'files' => ['a.txt']]],
            ['POST', '/files/decompress', SubuserPermission::FileCreate, [], ['root' => '/', 'file' => 'a.zip']],
            ['POST', '/files/delete', SubuserPermission::FileDelete, [], ['root' => '/', 'files' => ['a.txt']]],
            ['POST', '/files/create-folder', SubuserPermission::FileCreate, [], ['root' => '/', 'name' => 'dir']],
            ['POST', '/files/chmod', SubuserPermission::FileUpdate, [], ['root' => '/', 'files' => [['file' => 'a.txt', 'mode' => 644]]]],
            ['POST', '/files/pull', SubuserPermission::FileCreate, [], ['url' => 'https://example.com/a.txt', 'directory' => '/']],
            ['GET', '/files/upload', SubuserPermission::FileCreate, [], []],

            ['GET', '/schedules', SubuserPermission::ScheduleRead, [], []],
            ['POST', '/schedules', SubuserPermission::ScheduleCreate, [], ['name' => 'Test', 'is_active' => true, 'minute' => '*', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*']],
            ['GET', '/schedules/{schedule}', SubuserPermission::ScheduleRead, [], []],
            ['POST', '/schedules/{schedule}', SubuserPermission::ScheduleUpdate, [], ['name' => 'Renamed', 'is_active' => true, 'minute' => '*', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*']],
            ['POST', '/schedules/{schedule}/execute', SubuserPermission::ScheduleUpdate, [], []],
            ['DELETE', '/schedules/{schedule}', SubuserPermission::ScheduleDelete, [], []],
            ['POST', '/schedules/{schedule}/tasks', SubuserPermission::ScheduleUpdate, [SubuserPermission::ControlConsole], ['action' => 'command', 'payload' => 'say hi', 'time_offset' => 0]],
            ['POST', '/schedules/{schedule}/tasks/{task}', SubuserPermission::ScheduleUpdate, [SubuserPermission::ControlConsole], ['action' => 'command', 'payload' => 'say bye', 'time_offset' => 0]],
            ['DELETE', '/schedules/{schedule}/tasks/{task}', SubuserPermission::ScheduleDelete, [], []],

            ['GET', '/network/allocations', SubuserPermission::AllocationRead, [], []],
            ['POST', '/network/allocations', SubuserPermission::AllocationCreate, [], []],
            ['POST', '/network/allocations/{allocation}', SubuserPermission::AllocationUpdate, [], ['notes' => 'hello']],
            ['POST', '/network/allocations/{allocation}/primary', SubuserPermission::AllocationUpdate, [], []],
            ['DELETE', '/network/allocations/{allocation}', SubuserPermission::AllocationDelete, [], []],

            ['GET', '/users', SubuserPermission::UserRead, [], []],
            ['POST', '/users', SubuserPermission::UserCreate, [], ['email' => 'new-subuser@example.com', 'permissions' => ['websocket.connect']]],
            ['GET', '/users/{subuser}', SubuserPermission::UserRead, [], []],
            ['POST', '/users/{subuser}', SubuserPermission::UserUpdate, [], ['permissions' => ['websocket.connect']]],
            ['DELETE', '/users/{subuser}', SubuserPermission::UserDelete, [], []],

            ['GET', '/backups', SubuserPermission::BackupRead, [], []],
            ['POST', '/backups', SubuserPermission::BackupCreate, [], []],
            ['GET', '/backups/{backup}', SubuserPermission::BackupRead, [], []],
            ['GET', '/backups/{backup}/download', SubuserPermission::BackupDownload, [], []],
            ['PUT', '/backups/{backup}/rename', SubuserPermission::BackupDelete, [], ['name' => 'Renamed']],
            ['POST', '/backups/{backup}/lock', SubuserPermission::BackupDelete, [], []],
            ['POST', '/backups/{backup}/restore', SubuserPermission::BackupRestore, [], ['truncate' => false]],
            ['DELETE', '/backups/{backup}', SubuserPermission::BackupDelete, [], []],

            ['GET', '/startup', SubuserPermission::StartupRead, [], []],
            ['PUT', '/startup/variable', SubuserPermission::StartupUpdate, [], ['key' => 'SERVER_JARFILE', 'value' => 'server.jar']],

            ['POST', '/settings/rename', SubuserPermission::SettingsRename, [], ['name' => 'Renamed']],
            ['POST', '/settings/description', SubuserPermission::SettingsDescription, [], ['description' => 'Hello']],
            ['POST', '/settings/reinstall', SubuserPermission::SettingsReinstall, [], []],
            ['PUT', '/settings/docker-image', SubuserPermission::StartupDockerImage, [], ['docker_image' => 'ghcr.io/example/image:latest']],
        ];
    }

    /**
     * Endpoints an admin holding only "view server" may use: they can see the server, its
     * resource usage and its console, but not change anything.
     */
    private const VIEW_ONLY_ENDPOINTS = ['/', '/resources', '/websocket'];

    private function expectationFor(string $actor, string $path, ?string $permission): string
    {
        return match ($actor) {
            'owner', 'subuser_with_permission', 'root_admin', 'admin_update_server' => 'allow',
            // A subuser always reaches the server, so endpoints without a permission are open to them.
            'subuser_without_permission' => $permission === null ? 'allow' : 'forbid',
            'admin_view_server' => in_array($path, self::VIEW_ONLY_ENDPOINTS) ? 'allow' : 'forbid',
            // Admins restricted to other nodes must not even learn the server exists.
            'stranger', 'admin_update_server_other_node' => 'hidden',
            'application_key' => 'forbid',
        };
    }

    private function buildFixtures(): void
    {
        $this->server = $this->createServerModel([
            'database_limit' => 10,
            'allocation_limit' => 10,
            'backup_limit' => 10,
        ]);

        $this->extraAllocation = Allocation::factory()->create([
            'node_id' => $this->server->node_id,
            'server_id' => $this->server->id,
        ]);
        // Spare allocation so the auto-assign endpoint has something to hand out.
        Allocation::factory()->create(['node_id' => $this->server->node_id, 'ip' => $this->server->allocation->ip]);

        $host = DatabaseHost::factory()->create(['host' => '127.0.0.1', 'port' => 1]);
        $host->nodes()->sync([$this->server->node_id]);
        $this->database = Database::factory()->create([
            'server_id' => $this->server->id,
            'database_host_id' => $host->id,
        ]);

        $this->schedule = Schedule::factory()->create(['server_id' => $this->server->id]);
        $this->task = Task::factory()->create(['schedule_id' => $this->schedule->id, 'action' => 'command', 'payload' => 'say hi']);

        $this->backup = Backup::factory()->create([
            'server_id' => $this->server->id,
            'backup_host_id' => BackupHost::factory()->create()->id,
            'completed_at' => CarbonImmutable::now(),
            'is_successful' => true,
        ]);

        $this->targetSubuser = User::factory()->create();
        Subuser::query()->create([
            'user_id' => $this->targetSubuser->id,
            'server_id' => $this->server->id,
            'permissions' => [SubuserPermission::WebsocketConnect->value],
        ]);
    }

    /**
     * @param  string[]  $extra  permissions the endpoint needs on top of $permission (e.g. a task action)
     */
    private function authenticateAs(string $actor, ?string $permission, array $extra): void
    {
        $server = $this->server;

        switch ($actor) {
            case 'owner':
                $this->actingAs($server->user, 'web');
                break;
            case 'subuser_with_permission':
                $this->actingAs($this->makeSubuser(array_filter([SubuserPermission::WebsocketConnect->value, $permission, ...$extra])), 'web');
                break;
            case 'subuser_without_permission':
                $all = array_values(array_diff(Subuser::allPermissionKeys(), [$permission]));
                $this->actingAs($this->makeSubuser($all), 'web');
                break;
            case 'stranger':
                $this->actingAs(User::factory()->create(), 'web');
                break;
            case 'root_admin':
                $user = User::factory()->create();
                $user->syncRoles(Role::getRootAdmin());
                $this->actingAs($user, 'web');
                break;
            case 'admin_update_server':
                $this->actingAs($this->makeAdmin(['viewAny', 'view', 'update']), 'web');
                break;
            case 'admin_view_server':
                $this->actingAs($this->makeAdmin(['viewAny', 'view']), 'web');
                break;
            case 'admin_update_server_other_node':
                $this->actingAs($this->makeAdmin(['viewAny', 'view', 'update'], Node::factory()->create()), 'web');
                break;
            case 'application_key':
                $user = User::factory()->create();
                $user->syncRoles(Role::getRootAdmin());
                $key = ApiKey::factory()->create([
                    'user_id' => $user->id,
                    'key_type' => ApiKey::TYPE_APPLICATION,
                    'permissions' => [Server::RESOURCE_NAME => 3],
                ]);
                $this->withHeader('Accept', 'application/vnd.panel.v1+json')
                    ->withHeader('Authorization', 'Bearer ' . $key->identifier . $key->token);
                break;
        }
    }

    /**
     * @param  string[]  $permissions
     */
    private function makeSubuser(array $permissions): User
    {
        $user = User::factory()->create();
        Subuser::query()->create([
            'user_id' => $user->id,
            'server_id' => $this->server->id,
            'permissions' => array_values(array_unique($permissions)),
        ]);

        return $user;
    }

    /**
     * @param  string[]  $abilities  RolePermissionModels method names for the server model
     */
    private function makeAdmin(array $abilities, ?Node $restrictTo = null): User
    {
        $role = Role::factory()->create(['name' => 'Server Admin ' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(array_map(
            fn (string $ability) => Permission::findOrCreate(RolePermissionModels::Server->{$ability}(), 'web'),
            $abilities,
        ));

        if ($restrictTo) {
            $role->nodes()->sync([$restrictTo->id]);
        }

        $user = User::factory()->create();
        $user->syncRoles($role);

        return $user;
    }

    private function resolvePath(string $path): string
    {
        $path = preg_replace('/#\w+$/', '', $path);

        return strtr($path, [
            '{database}' => $this->database->id,
            '{schedule}' => $this->schedule->id,
            '{task}' => $this->task->id,
            '{allocation}' => $this->extraAllocation->id,
            '{subuser}' => $this->targetSubuser->uuid,
            '{backup}' => $this->backup->uuid,
        ]);
    }

    private function record(string $actor, string $method, string $path, ?string $permission, string $expected, int $status): void
    {
        if (!$file = getenv('PERMISSION_AUDIT_LOG')) {
            return;
        }

        file_put_contents($file, json_encode([
            'api' => 'client',
            'actor' => $actor,
            'method' => $method,
            'path' => $path,
            'permission' => $permission,
            'expected' => $expected,
            'status' => $status,
        ]) . PHP_EOL, FILE_APPEND);
    }
}
