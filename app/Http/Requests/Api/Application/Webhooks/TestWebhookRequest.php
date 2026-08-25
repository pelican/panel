<?php

namespace App\Http\Requests\Api\Application\Webhooks;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\WebhookConfiguration;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Contracts\Validation\ValidationRule;

class TestWebhookRequest extends ApplicationApiRequest
{
    protected ?string $resource = WebhookConfiguration::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /** @return array<string, string|array<string|\Stringable|ValidationRule>> */
    public function rules(): array
    {
        return [
            'event' => ['sometimes', 'string'],
            'data' => ['sometimes', 'array'],
        ];
    }
}
