<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\LaravelData\Attributes\MapOutputName;

final class UserData extends ApiResource
{
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
    ) {}

    public static function getResourceName(): string
    {
        return User::RESOURCE_NAME;
    }

    public static function fromModel(User $model): static
    {
        return new self(
            uuid: $model->uuid,
            username: $model->username,
            email: $model->email,
            language: $model->language,
            image: 'https://gravatar.com/avatar/' . md5(Str::lower($model->email)), // deprecated
            admin: $model->isRootAdmin(), // deprecated, use "root_admin"
            root_admin: $model->isRootAdmin(),
            two_factor_enabled: filled($model->mfa_app_secret),
            created_at: self::formatTimestamp($model->created_at),
            updated_at: self::formatTimestamp($model->updated_at),
        );
    }
}
