<?php

namespace App\Http\Requests\Api\Application\Plugins;

class ImportFilePluginRequest extends WritePluginRequest
{
    public function rules(): array
    {
        return [
            /** URL the plugin archive is downloaded from. */
            'url' => 'required|string',
            /** Base64 Ed25519 signature over the archive's SHA-256 digest; must match a trusted key. */
            'signature' => 'nullable|string',
        ];
    }
}
