<?php

namespace App\Http\Requests\Api\Application\Plugins;

class ImportFilePluginRequest extends WritePluginRequest
{
    public function rules(): array
    {
        return [
            /** URL the plugin archive is downloaded from. */
            'url' => 'required|string',
        ];
    }
}
