<?php

namespace App\Http\Requests\Api\Application\Webhooks;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\WebhookConfiguration;
use App\Services\Acl\Api\AdminAcl;

class DeleteWebhookRequest extends ApplicationApiRequest
{
    protected ?string $resource = WebhookConfiguration::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;
}
