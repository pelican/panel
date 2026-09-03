<?php

namespace App\Transformers\Api\Application;

use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\NullResource;

class UserTransformer extends BaseTransformer
{
    protected array $availableIncludes = [
        'servers',
        'roles',
    ];

    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return User::RESOURCE_NAME;
    }

    /**
     * @param  User  $user
     */
    public function transform($user): array
    {
        return [
            'id' => $user->id,
            'external_id' => $user->external_id,
            'is_managed_externally' => $user->is_managed_externally,
            'uuid' => $user->uuid,
            'username' => $user->username,
            'email' => $user->email,
            'language' => $user->language,
            'root_admin' => $user->isRootAdmin(),
            '2fa_enabled' => filled($user->mfa_app_secret),
            '2fa' => filled($user->mfa_app_secret), // deprecated, use "2fa_enabled"
            'created_at' => $this->formatTimestamp($user->created_at),
            'updated_at' => $this->formatTimestamp($user->updated_at),
        ];
    }

    /**
     * Return the servers associated with this user.
     */
    public function includeServers(User $user): Collection|NullResource
    {
        if (!$this->authorize(Server::RESOURCE_NAME)) {
            return $this->null();
        }

        $user->loadMissing('servers');

        return $this->collection($user->getRelation('servers'), $this->makeTransformer(ServerTransformer::class), 'server');
    }

    /**
     * Return the roles associated with this user.
     */
    public function includeRoles(User $user): Collection|NullResource
    {
        if (!$this->authorize(Role::RESOURCE_NAME)) {
            return $this->null();
        }

        $user->loadMissing('roles');

        return $this->collection($user->getRelation('roles'), $this->makeTransformer(RoleTransformer::class), Role::RESOURCE_NAME);
    }
}
