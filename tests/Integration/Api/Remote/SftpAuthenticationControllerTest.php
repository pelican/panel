<?php

namespace App\Tests\Integration\Api\Remote;

use App\Enums\ServerState;
use App\Enums\SubuserPermission;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Models\UserSSHKey;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;

class SftpAuthenticationControllerTest extends IntegrationTestCase
{
    protected User $user;

    protected Server $server;

    /**
     * Sets up the tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        [$user, $server] = $this->generateTestAccount();

        $user->update(['password' => password_hash('foobar', PASSWORD_DEFAULT)]);

        $this->user = $user;
        $this->server = $server;

        $this->setAuthorization();
    }

    /**
     * Test that a public key is validated correctly.
     */
    public function test_public_key_is_validated_correctly(): void
    {
        $key = UserSSHKey::factory()->for($this->user)->create();

        $this->postJson('/api/remote/sftp/auth', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.meta.source_field', 'username')
            ->assertJsonPath('errors.0.meta.rule', 'required')
            ->assertJsonPath('errors.1.meta.source_field', 'password')
            ->assertJsonPath('errors.1.meta.rule', 'required');

        $data = [
            'type' => 'public_key',
            'username' => $this->getUsername(),
            'password' => $key->public_key,
        ];

        $this->postJson('/api/remote/sftp/auth', $data)
            ->assertOk()
            ->assertJsonPath('server', $this->server->uuid)
            ->assertJsonPath('permissions', ['*']);

        $key->delete();
        $this->postJson('/api/remote/sftp/auth', $data)->assertForbidden();
        $this->postJson('/api/remote/sftp/auth', array_merge($data, ['type' => null]))->assertForbidden();
    }

    /**
     * Test that an account password is validated correctly.
     */
    public function test_password_is_validated_correctly(): void
    {
        $this->postJson('/api/remote/sftp/auth', [
            'username' => $this->getUsername(),
            'password' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.meta.source_field', 'password')
            ->assertJsonPath('errors.0.meta.rule', 'required');

        $this->postJson('/api/remote/sftp/auth', [
            'username' => $this->getUsername(),
            'password' => 'wrong password',
        ])
            ->assertForbidden();

        $this->user->update(['password' => password_hash('foobar', PASSWORD_DEFAULT)]);

        $this->postJson('/api/remote/sftp/auth', [
            'username' => $this->getUsername(),
            'password' => 'foobar',
        ])
            ->assertOk();
    }

    public function test_suspended_user_is_rejected_with_valid_credentials(): void
    {
        config()->set('mail.from.address', 'support@example.com');
        $this->user->forceFill(['suspended_at' => now()])->save();

        $this->postJson('/api/remote/sftp/auth', [
            'username' => $this->getUsername(),
            'password' => 'foobar',
        ])
            ->assertForbidden()
            ->assertJsonPath('errors.0.detail', 'Your account has been suspended. Please contact support@example.com for assistance.');
    }

    /**
     * Test that providing an invalid key and/or invalid username triggers the throttle on
     * the endpoint.
     */
    #[DataProvider('authorizationTypeDataProvider')]
    public function test_user_is_throttled_if_invalid_credentials_are_provided(string $type): void
    {
        for ($i = 0; $i <= 10; $i++) {
            $this->postJson('/api/remote/sftp/auth', [
                'type' => $type,
                'username' => $i % 2 === 0 ? $this->user->username : $this->getUsername(),
                'password' => 'invalid key',
            ])
                ->assertStatus($i === 10 ? 429 : 403);
        }
    }

    /**
     * Test that a request is rejected if the credentials are valid but the username indicates
     * a server on a different node.
     */
    #[DataProvider('authorizationTypeDataProvider')]
    public function test_request_is_rejected_if_server_belongs_to_different_node(string $type): void
    {
        $node2 = $this->createServerModel()->node;

        $this->setAuthorization($node2);

        $password = $type === 'public_key'
            ? UserSSHKey::factory()->for($this->user)->create()->public_key
            : 'foobar';

        $this->postJson('/api/remote/sftp/auth', [
            'type' => 'public_key',
            'username' => $this->getUsername(),
            'password' => $password,
        ])
            ->assertForbidden();
    }

    public function test_request_is_denied_if_user_lacks_sftp_permission(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::FileRead]);

        $user->update(['password' => password_hash('foobar', PASSWORD_DEFAULT)]);

        $this->setAuthorization($server->node);

        $this->postJson('/api/remote/sftp/auth', [
            'username' => $user->username . '.' . $server->uuid_short,
            'password' => 'foobar',
        ])
            ->assertForbidden()
            ->assertJsonPath('errors.0.detail', 'You do not have permission to access SFTP for this server.');
    }

    #[DataProvider('serverStateDataProvider')]
    public function test_invalid_server_state_returns_conflict_error(string $status): void
    {
        $this->server->update(['status' => $status]);

        $this->postJson('/api/remote/sftp/auth', ['username' => $this->getUsername(), 'password' => 'foobar'])
            ->assertStatus(409);
    }

    /**
     * Test that permissions are returned for the user account correctly.
     */
    public function test_user_permissions_are_returned_correctly(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::FileRead, SubuserPermission::FileSftp]);

        $user->update(['password' => password_hash('foobar', PASSWORD_DEFAULT)]);

        $this->setAuthorization($server->node);

        $data = [
            'username' => $user->username . '.' . $server->uuid_short,
            'password' => 'foobar',
        ];

        $this->postJson('/api/remote/sftp/auth', $data)
            ->assertOk()
            ->assertJsonPath('permissions', [SubuserPermission::FileRead->value, SubuserPermission::FileSftp->value]);

        $user->syncRoles(Role::getRootAdmin());

        $this->postJson('/api/remote/sftp/auth', $data)
            ->assertOk()
            ->assertJsonPath('permissions.0', '*');

        $this->setAuthorization();
        $data['username'] = $user->username . '.' . $this->server->uuid_short;

        $this->post('/api/remote/sftp/auth', $data)
            ->assertOk()
            ->assertJsonPath('permissions.0', '*');

        $user->syncRoles();
        $this->post('/api/remote/sftp/auth', $data)->assertForbidden();
    }

    public function test_subuser_sftp_works_when_user_has_view_only_role(): void
    {
        [$user, $server] = $this->generateTestAccount([SubuserPermission::FileRead, SubuserPermission::FileSftp]);

        $user->update(['password' => password_hash('foobar', PASSWORD_DEFAULT)]);

        $this->setAuthorization($server->node);

        $data = [
            'username' => $user->username . '.' . $server->uuid_short,
            'password' => 'foobar',
        ];

        // SFTP works as a plain subuser
        $this->postJson('/api/remote/sftp/auth', $data)
            ->assertOk()
            ->assertJsonPath('permissions', [SubuserPermission::FileRead->value, SubuserPermission::FileSftp->value]);

        // Assign a role with only "view server" permission
        $role = Role::findOrCreate('view-only-test', 'web');
        $permission = Permission::findOrCreate('view server', 'web');
        $role->givePermissionTo($permission);
        $user->syncRoles($role);

        // SFTP should still work — subuser permissions must be merged with admin permissions
        $response = $this->postJson('/api/remote/sftp/auth', $data)
            ->assertOk();

        $permissions = $response->json('permissions');
        $this->assertContains(SubuserPermission::FileSftp->value, $permissions);
        $this->assertContains(SubuserPermission::FileRead->value, $permissions);
    }

    public static function authorizationTypeDataProvider(): array
    {
        return [
            'password auth' => ['password'],
            'public key auth' => ['public_key'],
        ];
    }

    public static function serverStateDataProvider(): array
    {
        return [
            'installing' => [ServerState::Installing->value],
            'suspended' => [ServerState::Suspended->value],
            'restoring a backup' => [ServerState::RestoringBackup->value],
        ];
    }

    /**
     * Returns the username for connecting to SFTP.
     */
    protected function getUsername(bool $long = false): string
    {
        return $this->user->username . '.' . ($long ? $this->server->uuid : $this->server->uuid_short);
    }

    /**
     * Sets the authorization header for the rest of the test.
     */
    protected function setAuthorization(?Node $node = null): void
    {
        $node = $node ?? $this->server->node;

        $this->withHeader('Authorization', 'Bearer ' . $node->daemon_token_id . '.' . $node->daemon_token);
    }
}
