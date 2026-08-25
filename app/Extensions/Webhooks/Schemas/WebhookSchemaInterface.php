<?php

namespace App\Extensions\Webhooks\Schemas;

use App\Enums\WebhookScope;
use App\Models\WebhookConfiguration;
use BackedEnum;
use Filament\Schemas\Components\Component;
use Illuminate\Http\Client\Response;

interface WebhookSchemaInterface
{
    /**
     * Unique identifier for this type, stored in the `type` column of a webhook configuration.
     */
    public function getId(): string;

    public function getLabel(): string;

    public function getColor(): ?string;

    public function getIcon(): string|BackedEnum|null;

    /**
     * Components shown in the payload section when this type is selected.
     *
     * @return Component[]
     */
    public function getFormComponents(WebhookScope $scope): array;

    /**
     * Livewire component rendered next to the payload form as a live preview,
     * or null when this type has nothing to preview.
     */
    public function getPreviewComponent(): ?string;

    /**
     * Form keys forwarded to the preview component whenever the form changes. Dotted
     * names are resolved as nested paths, so a grouped field such as `author.name`
     * works. Keeping this list small avoids sending the whole form on every keystroke.
     *
     * @return string[]
     */
    public function getPreviewFields(): array;

    /**
     * Whether this type should be selected automatically for the given endpoint.
     */
    public function matchesEndpoint(string $endpoint): bool;

    /**
     * Validation rules applied to the stored payload, keyed relative to `payload`.
     * For example `['content' => ['string', 'max:2000']]` validates `payload.content`.
     *
     * @return array<string, mixed>
     */
    public function getPayloadRules(): array;

    /**
     * Turn form state into model attributes, usually by collapsing the type specific
     * fields into `payload`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeSave(array $data): array;

    /**
     * Turn model attributes back into form state, the inverse of mutateFormDataBeforeSave().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeFill(array $data): array;

    /**
     * Build the request body posted to the endpoint.
     *
     * @param  array<string, mixed>  $eventData
     * @return array<string, mixed>
     */
    public function preparePayload(WebhookConfiguration $webhookConfiguration, array $eventData): array;

    /**
     * Build the request headers.
     *
     * The prepared body is passed in as well as the raw event data, so a type that has to
     * sign its requests can compute an HMAC over exactly what will be sent.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $eventData
     * @return array<string, string>
     */
    public function prepareHeaders(WebhookConfiguration $webhookConfiguration, array $payload, array $eventData): array;

    /**
     * Send the payload to the endpoint.
     *
     * Owning the whole request means a type is free to change the verb, the encoding, the
     * timeout or the retry policy instead of being locked to a JSON POST.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function deliver(WebhookConfiguration $webhookConfiguration, array $payload, array $headers): Response;

    /**
     * Whether the response counts as a successful delivery. Override for endpoints that
     * report failures with a 2xx status and an error body.
     */
    public function wasSuccessful(Response $response): bool;

    /**
     * Seconds to wait before retrying a failed delivery, or null to not retry.
     * Lets a type honour rate limiting, such as Discord's `Retry-After` on a 429.
     */
    public function retryAfter(Response $response): ?int;
}
