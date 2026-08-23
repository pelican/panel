<?php

namespace App\Http\Requests\Api\Client\Account;

use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\UserSSHKey;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;
use phpseclib3\Crypt\Common\PublicKey;
use phpseclib3\Crypt\DSA;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Exception\NoKeyLoadedException;

class StoreSSHKeyRequest extends ClientApiRequest
{
    protected ?PublicKey $key;

    /** @return array<string, string|array<string|\Stringable|ValidationRule>> */
    public function rules(): array
    {
        return [
            /** Name the key is listed under in the Panel. */
            'name' => UserSSHKey::getRulesForField('name'),
            /** The public half of the SSH key, in OpenSSH format. */
            'public_key' => UserSSHKey::getRulesForField('public_key'),
        ];
    }

    /**
     * Check to see if this SSH key has already been added to the user's account
     * and if so return an error.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function () {
            try {
                $this->key = PublicKeyLoader::loadPublicKey($this->input('public_key'));
            } catch (NoKeyLoadedException $exception) {
                $this->validator->errors()->add('public_key', 'The public key provided is not valid.');

                return;
            }

            if ($this->key instanceof DSA) {
                $this->validator->errors()->add('public_key', 'DSA keys are not supported.');
            }

            if ($this->key instanceof RSA && $this->key->getLength() < 2048) {
                $this->validator->errors()->add('public_key', 'RSA keys must be at least 2048 bytes in length.');
            }

            $fingerprint = $this->key->getFingerprint('sha256');
            if ($this->user()->sshKeys()->where('fingerprint', $fingerprint)->exists()) {
                $this->validator->errors()->add('public_key', 'The public key provided already exists on your account.');
            }
        });
    }

    /**
     * Returns the public key but formatted in a consistent manner.
     */
    public function getPublicKey(): string
    {
        return $this->key->toString('PKCS8');
    }

    /**
     * Returns the SHA256 fingerprint of the key provided.
     */
    public function getKeyFingerprint(): string
    {
        throw_unless($this->key, new Exception('The public key was not properly loaded for this request.'));

        return $this->key->getFingerprint('sha256');
    }
}
