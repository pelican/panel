<?php

namespace App\Http\Requests\Api\Client\Account;

use App\Http\Requests\Api\Client\ClientApiRequest;
use App\Models\ApiKey;
use Exception;
use Illuminate\Validation\Validator;
use IPTools\Range;

class StoreApiKeyRequest extends ClientApiRequest
{
    /** @return array<array-key, string|string[]> */
    public function rules(): array
    {
        $rules = ApiKey::getRules();

        return [
            /** Note describing what the key is for. */
            'description' => $rules['memo'],
            /** IP addresses the key may be used from. Leave empty to allow any address. */
            'allowed_ips' => [...$rules['allowed_ips'], 'max:50'],
            /** An IPv4 or IPv6 address the key may be used from. */
            'allowed_ips.*' => 'string',
        ];
    }

    /**
     * Check that each of the values entered is actually valid.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!is_array($ips = $this->input('allowed_ips'))) {
                return;
            }

            foreach ($ips as $index => $ip) {
                $valid = false;
                try {
                    $valid = Range::parse($ip)->valid();
                } catch (Exception $exception) {
                    if ($exception->getMessage() !== 'Invalid IP address format') {
                        throw $exception;
                    }
                } finally {
                    $validator->errors()->addIf(!$valid, "allowed_ips.{$index}", '"' . $ip . '" is not a valid IP address or CIDR range.');
                }
            }
        });
    }
}
