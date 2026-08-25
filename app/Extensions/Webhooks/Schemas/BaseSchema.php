<?php

namespace App\Extensions\Webhooks\Schemas;

use App\Enums\TablerIcon;
use App\Enums\WebhookScope;
use App\Models\WebhookConfiguration;
use BackedEnum;
use Filament\Schemas\Components\Component;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class BaseSchema implements WebhookSchemaInterface
{
    abstract public function getId(): string;

    public function getLabel(): string
    {
        return Str::headline($this->getId());
    }

    public function getColor(): ?string
    {
        return null;
    }

    public function getIcon(): string|BackedEnum|null
    {
        return TablerIcon::Webhook;
    }

    /** @return Component[] */
    public function getFormComponents(WebhookScope $scope): array
    {
        return [];
    }

    public function getPreviewComponent(): ?string
    {
        return null;
    }

    /** @return string[] */
    public function getPreviewFields(): array
    {
        return [];
    }

    public function matchesEndpoint(string $endpoint): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function getPayloadRules(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    /**
     * @param  array<string, mixed>  $eventData
     * @return array<string, mixed>
     */
    public function preparePayload(WebhookConfiguration $webhookConfiguration, array $eventData): array
    {
        return $eventData;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $eventData
     * @return array<string, string>
     */
    public function prepareHeaders(WebhookConfiguration $webhookConfiguration, array $payload, array $eventData): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function deliver(WebhookConfiguration $webhookConfiguration, array $payload, array $headers): Response
    {
        return Http::withHeaders($headers)
            ->timeout($this->getTimeout())
            ->post($webhookConfiguration->endpoint, $payload);
    }

    public function wasSuccessful(Response $response): bool
    {
        return $response->successful();
    }

    public function retryAfter(Response $response): ?int
    {
        return null;
    }

    /**
     * Request timeout in seconds, used by the default deliver() implementation.
     */
    protected function getTimeout(): int
    {
        return (int) config('panel.webhook.timeout', 30);
    }
}
