<?php

namespace App\Http\Requests\Api\Application\Users;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\User;
use App\Services\Acl\Api\AdminAcl;
use Dedoc\Scramble\Attributes\BodyParameter;

// The rules come straight off the model, so there is no rules array to hang comments on. These
// describe the fields without touching the inferred types or the validation itself. Attributes
// are not inherited, so UpdateUserRequest repeats them against the same descriptions.
#[BodyParameter('email', description: self::FIELDS['email'])]
#[BodyParameter('external_id', description: self::FIELDS['external_id'])]
#[BodyParameter('is_managed_externally', description: self::FIELDS['is_managed_externally'])]
#[BodyParameter('username', description: self::FIELDS['username'])]
#[BodyParameter('password', description: self::FIELDS['password'])]
#[BodyParameter('language', description: self::FIELDS['language'])]
#[BodyParameter('timezone', description: self::FIELDS['timezone'])]
class StoreUserRequest extends ApplicationApiRequest
{
    /** @var array<string, string> */
    public const FIELDS = [
        'email' => 'Email address for the account. Must not already be in use.',
        'external_id' => 'Identifier from your own system, stored alongside the user so you can match the two up later.',
        'is_managed_externally' => 'Mark the account as managed elsewhere, which stops it being edited from within the Panel.',
        'username' => 'Username the account signs in with. Must not already be in use.',
        'password' => 'Password for the account. Leave empty to email the user a link to set their own.',
        'language' => 'Language the Panel is shown in for this user, as a locale code such as `en`.',
        'timezone' => 'Timezone dates are shown in for this user, such as `UTC` or `America/New_York`.',
    ];

    protected ?string $resource = User::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /**
     * @param  array<array-key, string|string[]> |null  $rules
     * @return array<array-key, string|string[]>
     */
    public function rules(?array $rules = null): array
    {
        $rules = $rules ?? User::getRules();

        return collect($rules)->only([
            'external_id',
            'is_managed_externally',
            'email',
            'username',
            'password',
            'language',
            'timezone',
        ])->toArray();
    }

    /**
     * Rename some fields to be more user-friendly.
     *
     * @return array<array-key, string>
     */
    public function attributes(): array
    {
        return [
            'external_id' => 'Third Party Identifier',
            'is_managed_externally' => 'Is managed by Third Party?',
        ];
    }
}
