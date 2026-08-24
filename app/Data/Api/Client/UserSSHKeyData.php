<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Models\UserSSHKey;

final class UserSSHKeyData extends ApiResource
{
    public function __construct(
        public string $name,
        public string $fingerprint,
        public string $public_key,
        public string $created_at,
    ) {}

    public static function getResourceName(): string
    {
        return UserSSHKey::RESOURCE_NAME;
    }

    public static function fromModel(UserSSHKey $model): static
    {
        return new self(
            name: $model->name,
            fingerprint: $model->fingerprint,
            public_key: $model->public_key,
            created_at: $model->created_at->toAtomString(),
        );
    }
}
