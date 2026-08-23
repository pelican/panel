<?php

namespace App\Http\Requests\Api\Client\Account;

use App\Exceptions\Http\Base\InvalidPasswordProvidedException;
use App\Http\Requests\Api\Client\ClientApiRequest;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Container\Container;
use Illuminate\Contracts\Hashing\Hasher;

// current_password is checked in authorize() rather than validated, so it has no rule to comment.
#[BodyParameter('current_password', description: 'The account\'s current password, which must be given to change it.', required: true, type: 'string')]
#[BodyParameter('password_confirmation', description: 'Repeat of the new password.', required: true)]
class UpdatePasswordRequest extends ClientApiRequest
{
    /**
     * @throws InvalidPasswordProvidedException
     */
    public function authorize(): bool
    {
        if (!parent::authorize()) {
            return false;
        }

        $hasher = Container::getInstance()->make(Hasher::class);

        // Verify password matches when changing password or email.
        throw_unless($hasher->check($this->input('current_password'), $this->user()->password), new InvalidPasswordProvidedException(trans('validation.internal.invalid_password')));

        return !$this->user()->is_managed_externally;
    }

    public function rules(): array
    {
        return [
            /** New password. */
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ];
    }
}
