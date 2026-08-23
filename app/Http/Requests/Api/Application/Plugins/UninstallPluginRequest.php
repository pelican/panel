<?php

namespace App\Http\Requests\Api\Application\Plugins;

class UninstallPluginRequest extends WritePluginRequest
{
    /**
     * @param  array<array-key, string|string[]>|null  $rules
     * @return array<array-key, string|string[]>
     */
    public function rules(?array $rules = null): array
    {
        return [
            /** Also remove the plugin's files from disk rather than only disabling it. */
            'delete' => 'boolean',
        ];
    }
}
