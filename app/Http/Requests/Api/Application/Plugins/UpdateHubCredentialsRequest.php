<?php

namespace App\Http\Requests\Api\Application\Plugins;

class UpdateHubCredentialsRequest extends WritePluginRequest
{
    public function rules(): array
    {
        return [
            /** Base URL of the Pelican Hub this panel is connected to. */
            'hub_url' => ['required', 'string', 'max:255', 'url:https', 'regex:/^\S+$/'],
            /** The panel's Hub-issued key, sent back to the Hub on plugin update checks and downloads. */
            'api_key' => ['required', 'string', 'max:128', 'regex:/^pnl_[A-Za-z0-9]+$/'],
        ];
    }
}
