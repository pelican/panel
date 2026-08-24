<?php

namespace App\Http\Requests\Api\Application\Webhooks;

use App\Enums\WebhookScope;
use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\WebhookConfiguration;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class GetWebhookRequest extends ApplicationApiRequest
{
    /**
     * Largest page a client may ask for.
     */
    public const MaxPerPage = 100;

    public const DefaultPerPage = 50;

    protected ?string $resource = WebhookConfiguration::RESOURCE_NAME;

    protected int $permission = AdminAcl::READ;

    /** @return array<string, string|array<string|\Stringable|ValidationRule>> */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:' . self::MaxPerPage],
            'scope' => ['sometimes', Rule::enum(WebhookScope::class)],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::DefaultPerPage);
    }

    public function scope(): WebhookScope
    {
        return WebhookScope::tryFrom((string) $this->validated('scope')) ?? WebhookScope::Global;
    }
}
