<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Models\Subuser;
use Spatie\LaravelData\Attributes\MapOutputName;

final class SubuserData extends ApiResource
{
    /**
     * @param  string[]  $permissions
     */
    public function __construct(
        public string $uuid,
        public string $username,
        public string $email,
        public ?string $language,
        public string $image,
        public bool $admin,
        public bool $root_admin,
        #[MapOutputName('2fa_enabled')]
        public bool $two_factor_enabled,
        public string $created_at,
        public string $updated_at,
        public array $permissions,
    ) {}

    public static function getResourceName(): string
    {
        return Subuser::RESOURCE_NAME;
    }

    public static function fromModel(Subuser $model): static
    {
        $user = UserData::fromModel($model->user);

        return new self(
            uuid: $user->uuid,
            username: $user->username,
            email: $user->email,
            language: $user->language,
            image: $user->image,
            admin: $user->admin,
            root_admin: $user->root_admin,
            two_factor_enabled: $user->two_factor_enabled,
            created_at: $user->created_at,
            updated_at: $user->updated_at,
            permissions: $model->permissions,
        );
    }
}
