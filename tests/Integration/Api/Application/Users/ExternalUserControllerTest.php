<?php

namespace App\Tests\Integration\Api\Application\Users;

use App\Models\User;
use App\Services\Acl\Api\AdminAcl;
use App\Tests\Integration\Api\Application\ApplicationApiIntegrationTestCase;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ExternalUserControllerTest extends ApplicationApiIntegrationTestCase
{
    /**
     * Test that a user can be retrieved by their external ID.
     */
    public function test_get_remote_user(): void
    {
        $user = User::factory()->create(['external_id' => Str::random()]);

        $response = $this->getJson('/api/application/users/external/' . $user->external_id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2);
        $response->assertJsonStructure([
            'object',
            'attributes' => [
                'id', 'external_id', 'is_managed_externally', 'uuid', 'username', 'email',
                'language', 'root_admin', '2fa', 'created_at', 'updated_at',
            ],
        ]);

        $response->assertJson([
            'object' => 'user',
            'attributes' => [
                'id' => $user->id,
                'external_id' => $user->external_id,
                'is_managed_externally' => $user->is_managed_externally,
                'uuid' => $user->uuid,
                'username' => $user->username,
                'email' => $user->email,
                'language' => $user->language,
                'root_admin' => (bool) $user->isRootAdmin(),
                '2fa' => filled($user->mfa_app_secret),
                'created_at' => $this->formatTimestamp($user->created_at),
                'updated_at' => $this->formatTimestamp($user->updated_at),
            ],
        ], true);
    }

    /**
     * Test that an invalid external ID returns a 404 error.
     */
    public function test_get_missing_user(): void
    {
        $response = $this->getJson('/api/application/users/external/12345');
        $this->assertNotFoundJson($response);
    }

    /**
     * Test that an authentication error occurs if a key does not have permission
     * to access a resource.
     */
    public function test_error_returned_if_no_permission(): void
    {
        $user = User::factory()->create(['external_id' => Str::random()]);
        $this->createNewDefaultApiKey($this->getApiUser(), [User::RESOURCE_NAME => AdminAcl::NONE]);

        $response = $this->getJson('/api/application/users/external/' . $user->external_id);
        $this->assertAccessDeniedJson($response);
    }
}
