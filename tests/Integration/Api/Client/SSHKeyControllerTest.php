<?php

namespace App\Tests\Integration\Api\Client;

use App\Models\User;
use App\Models\UserSSHKey;
use phpseclib4\Crypt\EC;

class SSHKeyControllerTest extends ClientApiIntegrationTestCase
{
    /**
     * Test that only the SSH keys for the authenticated user are returned.
     */
    public function test_ssh_keys_are_returned(): void
    {
        $user = User::factory()->create();
        $user2 = User::factory()->create();

        $key = UserSSHKey::factory()->for($user)->create();
        UserSSHKey::factory()->for($user2)->rsa()->create();

        $this->actingAs($user);
        $response = $this->getJson('/api/client/account/ssh-keys')
            ->assertOk()
            ->assertJsonPath('object', 'list')
            ->assertJsonPath('data.0.object', UserSSHKey::RESOURCE_NAME);

        $this->assertJsonTransformedWith($response->json('data.0.attributes'), $key);
    }

    /**
     * Test that a user's SSH key can be deleted, and that passing the fingerprint
     * of another user's SSH key won't delete that key.
     */
    public function test_ssh_key_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $user2 = User::factory()->create();

        $key = UserSSHKey::factory()->for($user)->create();
        $key2 = UserSSHKey::factory()->for($user2)->create();

        $this->actingAs($user);
        $this->delete('/api/client/account/ssh-keys/' . $key->fingerprint)->assertNoContent();

        $this->assertSoftDeleted($key);
        $this->assertNotSoftDeleted($key2);

        $this->delete('/api/client/account/ssh-keys/' . $key2->fingerprint)->assertNotFound();

        $this->assertNotSoftDeleted($key2);
    }

    public function test_dsa_key_is_rejected(): void
    {
        $user = User::factory()->create();
        $key = UserSSHKey::factory()->dsa()->make();

        $this->actingAs($user)->postJson('/api/client/account/ssh-keys', [
            'name' => 'Name',
            'public_key' => $key->public_key,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.detail', 'DSA keys are not supported.');

        $this->assertEquals(0, $user->sshKeys()->count());
    }

    public function test_weak_rsa_key_is_rejected(): void
    {
        $user = User::factory()->create();
        $key = UserSSHKey::factory()->rsa(true)->make();

        $this->actingAs($user)->postJson('/api/client/account/ssh-keys', [
            'name' => 'Name',
            'public_key' => $key->public_key,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.detail', 'RSA keys must be at least 2048 bytes in length.');

        $this->assertEquals(0, $user->sshKeys()->count());
    }

    public function test_invalid_or_private_key_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/client/account/ssh-keys', [
            'name' => 'Name',
            'public_key' => 'invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.detail', 'The public key provided is not valid.');

        $this->assertEquals(0, $user->sshKeys()->count());

        $key = EC::createKey('Ed25519');
        $this->actingAs($user)->postJson('/api/client/account/ssh-keys', [
            'name' => 'Name',
            'public_key' => $key->toString('PKCS8'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.detail', 'The public key provided is not valid.');
    }

    public function test_public_key_can_be_stored(): void
    {
        $user = User::factory()->create();
        $key = UserSSHKey::factory()->make();

        $this->actingAs($user)->postJson('/api/client/account/ssh-keys', [
            'name' => 'Name',
            'public_key' => $key->public_key,
        ])
            ->assertOk()
            ->assertJsonPath('object', UserSSHKey::RESOURCE_NAME)
            ->assertJsonPath('attributes.public_key', $key->public_key);

        $this->assertCount(1, $user->sshKeys);
        $this->assertEquals($key->public_key, $user->sshKeys[0]->public_key);
    }

    public function test_public_key_that_already_exists_cannot_be_added_a_second_time(): void
    {
        $user = User::factory()->create();
        $key = UserSSHKey::factory()->for($user)->create();

        $this->actingAs($user)->postJson('/api/client/account/ssh-keys', [
            'name' => 'Name',
            'public_key' => $key->public_key,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.detail', 'The public key provided already exists on your account.');

        $this->assertEquals(1, $user->sshKeys()->count());
    }
}
