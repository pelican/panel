<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Spatie\LaravelData\Attributes\MapOutputName;

final class UserData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = [
        'servers',
        'roles',
    ];

    public function __construct(
        public int $id,
        public ?string $external_id,
        public bool $is_managed_externally,
        public string $uuid,
        public string $username,
        public string $email,
        public ?string $language,
        public bool $root_admin,
        public bool $suspended,
        public ?string $suspended_at,
        #[MapOutputName('2fa_enabled')]
        public bool $twoFactorEnabled,
        #[MapOutputName('2fa')]
        public bool $twoFactorLegacy,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return User::RESOURCE_NAME;
    }

    public static function fromModel(User $model): static
    {
        return new self(
            id: $model->id,
            external_id: $model->external_id,
            is_managed_externally: $model->is_managed_externally,
            uuid: $model->uuid,
            username: $model->username,
            email: $model->email,
            language: $model->language,
            root_admin: $model->isRootAdmin(),
            suspended: $model->isSuspended(),
            suspended_at: $model->suspended_at ? self::formatTimestamp($model->suspended_at) : null,
            twoFactorEnabled: filled($model->mfa_app_secret),
            twoFactorLegacy: filled($model->mfa_app_secret), // deprecated, use "2fa_enabled"
            created_at: self::formatTimestamp($model->created_at),
            updated_at: self::formatTimestamp($model->updated_at),
        );
    }

    public static function includes(): array
    {
        return [
            'servers' => function (User $user, IncludeContext $context): array {
                if (!$context->allowsAdmin(Server::RESOURCE_NAME)) {
                    return $context->null();
                }

                $user->loadMissing('servers');

                return $context->collection($user->getRelation('servers'), ServerData::class);
            },
            'roles' => function (User $user, IncludeContext $context): array {
                if (!$context->allowsAdmin(Role::RESOURCE_NAME)) {
                    return $context->null();
                }

                $user->loadMissing('roles');

                return $context->collection($user->getRelation('roles'), RoleData::class);
            },
        ];
    }
}
