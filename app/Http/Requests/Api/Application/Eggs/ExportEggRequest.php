<?php

namespace App\Http\Requests\Api\Application\Eggs;

class ExportEggRequest extends GetEggRequest
{
    public function rules(): array
    {
        return [
            /** Format the exported egg is returned in. Defaults to `json`. */
            'format' => 'nullable|string|in:yaml,json',
        ];
    }
}
