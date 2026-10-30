<?php

namespace App\Http\Requests\Api\Application\Users;

use App\Http\Requests\Api\Application\Users\Concerns\ProtectsRootAdmins;
use App\Models\User;
use Dedoc\Scramble\Attributes\BodyParameter;

// Attributes are not inherited, so these repeat the parent's against the same descriptions.
#[BodyParameter('email', description: self::FIELDS['email'])]
#[BodyParameter('external_id', description: self::FIELDS['external_id'])]
#[BodyParameter('is_managed_externally', description: self::FIELDS['is_managed_externally'])]
#[BodyParameter('username', description: self::FIELDS['username'])]
#[BodyParameter('password', description: self::FIELDS['password'])]
#[BodyParameter('language', description: self::FIELDS['language'])]
#[BodyParameter('timezone', description: self::FIELDS['timezone'])]
class UpdateUserRequest extends StoreUserRequest
{
    use ProtectsRootAdmins;

    public function authorize(): bool
    {
        if (!parent::authorize()) {
            return false;
        }

        $this->denyIfTargetIsRootAdmin();

        return true;
    }

    /**
     * @param  array<array-key, string|string[]> |null  $rules
     * @return array<array-key, string|string[]>
     */
    public function rules(?array $rules = null): array
    {
        // No route to read the user off of when the rules are being inspected rather than applied,
        // which is how the API documentation is generated.
        if (!$this->route()) {
            return parent::rules($rules);
        }

        $user = $this->parameter('user', User::class);

        return parent::rules(User::getRulesForUpdate($user));
    }
}
