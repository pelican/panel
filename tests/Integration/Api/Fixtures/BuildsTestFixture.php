<?php

namespace App\Tests\Integration\Api\Fixtures;

use App\Models\ActivityLog;
use App\Models\Allocation;
use App\Models\ApiKey;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Models\Subuser;
use App\Models\Task;
use App\Models\User;
use App\Models\UserSSHKey;
use App\Services\Acl\Api\AdminAcl;
use Spatie\Permission\Models\Permission;

/**
 * Builds one canonical, fully pinned model graph for the API test fixture suite. Every
 * attribute that surfaces in an API response is set explicitly so consecutive runs,
 * parallel workers, and CI all produce identical JSON. Nothing here relies on the
 * seeded eggs, whose import is allowed to fail silently in the base TestCase.
 *
 * These tests only run against sqlite (see the api-fixtures group exclusion in CI),
 * so creating rows with explicit ids is safe.
 */
trait BuildsTestFixture
{
    protected User $fixtureAdmin;

    protected ApiKey $fixtureAdminKey;

    protected User $fixtureOwner;

    protected ApiKey $fixtureOwnerKey;

    protected User $fixtureSubuser;

    protected ApiKey $fixtureSubuserKey;

    protected Role $supportRole;

    protected Node $fixtureNode;

    protected Allocation $primaryAllocation;

    protected Allocation $secondaryAllocation;

    protected Allocation $unassignedAllocation;

    protected Egg $fixtureEgg;

    protected EggVariable $visibleVariable;

    protected EggVariable $hiddenVariable;

    protected Server $fixtureServer;

    protected DatabaseHost $fixtureDatabaseHost;

    protected Database $fixtureDatabase;

    protected Mount $fixtureMount;

    protected Backup $fixtureBackup;

    protected Schedule $fixtureSchedule;

    protected Task $fixtureTask;

    protected UserSSHKey $fixtureSshKey;

    protected Subuser $fixtureSubuserPivot;

    protected ActivityLog $fixtureActivityLog;

    protected ActivityLog $fixtureAccountActivityLog;

    protected function buildTestFixture(): void
    {
        $this->fixtureAdmin = User::factory()->create([
            'id' => 100,
            'uuid' => '00000000-0000-4000-8000-000000000100',
            'username' => 'fixture.admin',
            'email' => 'admin@fixture.test',
        ]);
        $this->fixtureAdmin->syncRoles(Role::getRootAdmin());

        $this->fixtureOwner = User::factory()->create([
            'id' => 101,
            'uuid' => '00000000-0000-4000-8000-000000000101',
            'username' => 'fixture.owner',
            'email' => 'owner@fixture.test',
            'external_id' => 'fixture-owner-ext',
        ]);

        $this->fixtureSubuser = User::factory()->create([
            'id' => 102,
            'uuid' => '00000000-0000-4000-8000-000000000102',
            'username' => 'fixture.subuser',
            'email' => 'subuser@fixture.test',
        ]);

        // Role and Permission guard their id column, so these land on the next
        // autoincrement values; sqlite truncation resets those, keeping them stable.
        $this->supportRole = Role::create(['name' => 'Support']);
        $this->supportRole->givePermissionTo(Permission::create(['name' => 'viewList user']));
        $this->fixtureOwner->syncRoles($this->supportRole);

        $this->fixtureAdminKey = ApiKey::factory()->create([
            'id' => 100,
            'user_id' => $this->fixtureAdmin->id,
            'key_type' => ApiKey::TYPE_APPLICATION,
            'identifier' => 'papp_fixture0001',
            'token' => 'fixtureapplicationtokenvalue0001',
            'memo' => 'Fixture application key',
            'permissions' => [
                Server::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Node::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Allocation::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                User::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Egg::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                DatabaseHost::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Database::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Mount::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Role::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
            ],
        ]);

        $this->fixtureOwnerKey = ApiKey::factory()->create([
            'id' => 101,
            'user_id' => $this->fixtureOwner->id,
            'key_type' => ApiKey::TYPE_ACCOUNT,
            'identifier' => 'pacc_fixture0001',
            'token' => 'fixtureaccounttokenvalue00000001',
            'memo' => 'Fixture account key',
        ]);

        $this->fixtureSubuserKey = ApiKey::factory()->create([
            'id' => 102,
            'user_id' => $this->fixtureSubuser->id,
            'key_type' => ApiKey::TYPE_ACCOUNT,
            'identifier' => 'pacc_fixture0002',
            'token' => 'fixtureaccounttokenvalue00000002',
            'memo' => 'Fixture subuser key',
        ]);

        $this->fixtureNode = Node::factory()->create([
            'id' => 100,
            'name' => 'fixture-node',
            'fqdn' => 'node.fixture.test',
            'tags' => ['fixture'],
        ]);

        // Node::creating always regenerates the uuid and daemon tokens, so the pinned
        // values have to be restored after the fact; saveQuietly keeps the observer out
        // of the way while the encrypted cast still applies to the token.
        $this->fixtureNode->forceFill([
            'uuid' => '00000000-0000-4000-8000-000000000200',
            'daemon_token_id' => 'fixturenodetoken',
            'daemon_token' => str_pad('fixturenodedaemontokenvalue', Node::DAEMON_TOKEN_LENGTH, '0'),
        ])->saveQuietly();

        $this->primaryAllocation = Allocation::factory()->create([
            'id' => 100,
            'node_id' => $this->fixtureNode->id,
            'ip' => '192.0.2.10',
            'port' => 25565,
            'notes' => null,
        ]);

        $this->secondaryAllocation = Allocation::factory()->create([
            'id' => 101,
            'node_id' => $this->fixtureNode->id,
            'ip' => '192.0.2.10',
            'port' => 25566,
            'notes' => 'Secondary allocation',
        ]);

        $this->unassignedAllocation = Allocation::factory()->create([
            'id' => 102,
            'node_id' => $this->fixtureNode->id,
            'ip' => '192.0.2.10',
            'port' => 25567,
            'notes' => null,
        ]);

        $this->fixtureEgg = Egg::factory()->create([
            'id' => 100,
            'uuid' => '00000000-0000-4000-8000-000000000300',
            'name' => 'Fixture Egg',
            'author' => 'egg@fixture.test',
            'description' => 'A pinned egg for the API test fixture suite.',
            'docker_images' => ['Java 21' => 'ghcr.io/fixture/java:21'],
            'startup_commands' => ['java -jar server.jar'],
        ]);

        $this->visibleVariable = EggVariable::factory()->create([
            'id' => 100,
            'egg_id' => $this->fixtureEgg->id,
            'name' => 'Server Jar',
            'description' => 'The jar file to run.',
            'env_variable' => 'SERVER_JARFILE',
            'default_value' => 'server.jar',
            'user_viewable' => true,
            'user_editable' => true,
            'rules' => ['required', 'string'],
        ]);

        $this->hiddenVariable = EggVariable::factory()->create([
            'id' => 101,
            'egg_id' => $this->fixtureEgg->id,
            'name' => 'Hidden Token',
            'description' => 'An internal variable users cannot see.',
            'env_variable' => 'HIDDEN_TOKEN',
            'default_value' => 'internal',
            'user_viewable' => false,
            'user_editable' => false,
            'rules' => ['required', 'string'],
        ]);

        $this->fixtureServer = Server::factory()->create([
            'id' => 100,
            'uuid' => '00000000-0000-4000-8000-000000000400',
            'uuid_short' => 'fixture',
            'external_id' => 'fixture-external-1',
            'name' => 'Fixture Server',
            'description' => 'A pinned server for the API test fixture suite.',
            'owner_id' => $this->fixtureOwner->id,
            'node_id' => $this->fixtureNode->id,
            'allocation_id' => $this->primaryAllocation->id,
            'egg_id' => $this->fixtureEgg->id,
            'startup' => 'java -jar {{SERVER_JARFILE}}',
            'image' => 'ghcr.io/fixture/java:21',
        ]);

        Allocation::query()->whereIn('id', [$this->primaryAllocation->id, $this->secondaryAllocation->id])
            ->update(['server_id' => $this->fixtureServer->id]);

        ServerVariable::query()->forceCreate([
            'id' => 100,
            'server_id' => $this->fixtureServer->id,
            'variable_id' => $this->visibleVariable->id,
            'variable_value' => 'custom.jar',
        ]);

        ServerVariable::query()->forceCreate([
            'id' => 101,
            'server_id' => $this->fixtureServer->id,
            'variable_id' => $this->hiddenVariable->id,
            'variable_value' => 'internal',
        ]);

        $this->fixtureSubuserPivot = Subuser::query()->forceCreate([
            'id' => 100,
            'user_id' => $this->fixtureSubuser->id,
            'server_id' => $this->fixtureServer->id,
            'permissions' => ['control.console', 'control.start', 'websocket.connect'],
        ]);

        $this->fixtureDatabaseHost = DatabaseHost::factory()->create([
            'id' => 100,
            'name' => 'fixture-database-host',
            'host' => '192.0.2.20',
            'port' => 3306,
            'username' => 'fixture',
            'password' => 'fixture-host-password',
        ]);
        $this->fixtureDatabaseHost->nodes()->sync([$this->fixtureNode->id]);

        $this->fixtureDatabase = Database::factory()->create([
            'id' => 100,
            'server_id' => $this->fixtureServer->id,
            'database_host_id' => $this->fixtureDatabaseHost->id,
            'database' => 's100_fixture',
            'username' => 'u100_fixture',
            'password' => 'fixture-database-password',
            'remote' => '%',
        ]);

        $this->fixtureMount = Mount::query()->forceCreate([
            'id' => 100,
            'uuid' => '00000000-0000-4000-8000-000000000500',
            'name' => 'fixture-mount',
            'description' => 'A pinned mount for the API test fixture suite.',
            'source' => '/srv/fixture',
            'target' => '/mnt/fixture',
            'read_only' => true,
            'user_mountable' => false,
        ]);
        $this->fixtureMount->eggs()->sync([$this->fixtureEgg->id]);
        $this->fixtureMount->nodes()->sync([$this->fixtureNode->id]);
        $this->fixtureMount->servers()->sync([$this->fixtureServer->id]);

        $backupHost = BackupHost::factory()->create([
            'id' => 100,
            'name' => 'fixture-backup-host',
        ]);

        $this->fixtureBackup = Backup::factory()->create([
            'id' => 100,
            'server_id' => $this->fixtureServer->id,
            'backup_host_id' => $backupHost->id,
            'uuid' => '00000000-0000-4000-8000-000000000600',
            'name' => 'Fixture Backup',
            'bytes' => 1024,
            'is_locked' => false,
        ]);

        $this->fixtureSchedule = Schedule::query()->forceCreate([
            'id' => 100,
            'server_id' => $this->fixtureServer->id,
            'name' => 'Fixture Schedule',
            'cron_day_of_week' => '*',
            'cron_month' => '*',
            'cron_day_of_month' => '*',
            'cron_hour' => '0',
            'cron_minute' => '0',
            'is_active' => true,
            'is_processing' => false,
            'only_when_online' => false,
            'next_run_at' => '2026-01-02 00:00:00',
        ]);

        $this->fixtureTask = Task::factory()->create([
            'id' => 100,
            'schedule_id' => $this->fixtureSchedule->id,
            'sequence_id' => 1,
            'action' => 'command',
            'payload' => 'say restarting soon',
            'time_offset' => 0,
        ]);

        $this->fixtureSshKey = UserSSHKey::factory()->create([
            'id' => 100,
            'user_id' => $this->fixtureOwner->id,
            'name' => 'Fixture SSH Key',
        ]);

        $this->fixtureActivityLog = ActivityLog::query()->forceCreate([
            'id' => 100,
            'event' => 'server:power.start',
            'ip' => '192.0.2.1',
            'description' => null,
            'actor_type' => 'user',
            'actor_id' => $this->fixtureOwner->id,
            'api_key_id' => null,
            'properties' => [],
            'timestamp' => now(),
        ]);
        $this->fixtureActivityLog->subjects()->forceCreate([
            'subject_type' => 'server',
            'subject_id' => $this->fixtureServer->id,
        ]);

        // The account activity endpoint lists logs whose subject is the user, so it
        // needs its own entry; the server-subject log above never shows up there.
        $this->fixtureAccountActivityLog = ActivityLog::query()->forceCreate([
            'id' => 101,
            'event' => 'user:account.email-changed',
            'ip' => '192.0.2.1',
            'description' => null,
            'actor_type' => 'user',
            'actor_id' => $this->fixtureOwner->id,
            'api_key_id' => null,
            'properties' => [],
            'timestamp' => now(),
        ]);
        $this->fixtureAccountActivityLog->subjects()->forceCreate([
            'subject_type' => 'user',
            'subject_id' => $this->fixtureOwner->id,
        ]);
    }

    protected function actingAsFixtureAdmin(): static
    {
        return $this
            ->withHeader('Accept', 'application/vnd.panel.v1+json')
            ->withHeader('Authorization', 'Bearer ' . $this->fixtureAdminKey->identifier . 'fixtureapplicationtokenvalue0001');
    }

    protected function actingAsFixtureOwner(): static
    {
        return $this
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->fixtureOwnerKey->identifier . 'fixtureaccounttokenvalue00000001');
    }

    protected function actingAsFixtureSubuser(): static
    {
        return $this
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->fixtureSubuserKey->identifier . 'fixtureaccounttokenvalue00000002');
    }
}
