<?php

namespace App\Tests\Integration\Api\Application;

use App\Models\Allocation;
use App\Models\ApiKey;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Egg;
use App\Models\Mount;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use App\Models\WebhookConfiguration;
use App\Services\Acl\Api\AdminAcl;
use App\Tests\Integration\IntegrationTestCase;
use App\Tests\Traits\Http\IntegrationJsonRequestAssertions;
use App\Tests\Traits\Integration\CreatesTestModels;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

abstract class ApplicationApiIntegrationTestCase extends IntegrationTestCase
{
    use CreatesTestModels;
    use IntegrationJsonRequestAssertions;

    private ApiKey $key;

    private User $user;

    /**
     * Bootstrap application API tests. Creates a default admin user and associated API key
     * and also sets some default headers required for accessing the API.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createApiUser();
        $this->key = $this->createApiKey($this->user);

        $this
            ->withHeader('Accept', 'application/vnd.panel.v1+json')
            ->withHeader('Authorization', 'Bearer ' . $this->key->identifier . $this->key->token);
    }

    public function getApiUser(): User
    {
        return $this->user;
    }

    public function getApiKey(): ApiKey
    {
        return $this->key;
    }

    /**
     * Creates a new default API key and refreshes the headers using it.
     */
    protected function createNewDefaultApiKey(User $user, array $permissions = []): ApiKey
    {
        $this->key = $this->createApiKey($user, $permissions);

        $this->withHeader('Authorization', 'Bearer ' . $this->key->identifier . $this->key->token);

        return $this->key;
    }

    /**
     * Create an administrative user.
     */
    protected function createApiUser(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(Role::getRootAdmin());

        return $user;
    }

    /**
     * Create a new application API key for a given user model.
     */
    protected function createApiKey(User $user, array $permissions = []): ApiKey
    {
        return ApiKey::factory()->create([
            'user_id' => $user->id,
            'key_type' => ApiKey::TYPE_APPLICATION,
            'permissions' => array_merge([
                Server::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Node::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Allocation::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                User::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Egg::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                DatabaseHost::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Database::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Mount::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                Role::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
                WebhookConfiguration::RESOURCE_NAME => AdminAcl::READ | AdminAcl::WRITE,
            ], $permissions),
        ]);
    }

    /**
     * Return the attributes the given application Data class produces for a model,
     * hydrated through the container the same way the response envelope does.
     */
    protected function getExpectedData(string $dataClass, mixed $model): array
    {
        $request = Request::createFromGlobals();
        $request->setUserResolver(function () {
            return $this->getApiKey()->user;
        });

        return Container::getInstance()
            ->call([$dataClass, 'fromModel'], ['model' => $model, 'request' => $request])
            ->toArray();
    }
}
