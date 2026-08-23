<?php

namespace App\Http\Controllers\Api\Application\Webhooks;

use App\Extensions\Webhooks\Schemas\WebhookSchemaInterface;
use App\Facades\WebhookTypes;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Webhooks\DeleteWebhookRequest;
use App\Http\Requests\Api\Application\Webhooks\GetWebhookRequest;
use App\Http\Requests\Api\Application\Webhooks\StoreWebhookRequest;
use App\Http\Requests\Api\Application\Webhooks\TestWebhookRequest;
use App\Http\Requests\Api\Application\Webhooks\UpdateWebhookRequest;
use App\Models\WebhookConfiguration;
use App\Transformers\Api\Application\WebhookConfigurationTransformer;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

class WebhookController extends ApplicationApiController
{
    /**
     * List webhooks
     *
     * Return all the webhooks currently configured on the Panel.
     *
     * @return array<array-key, mixed>
     */
    public function index(GetWebhookRequest $request): array
    {
        $webhooks = QueryBuilder::for(WebhookConfiguration::class)
            ->allowedFilters(['name', 'description', 'endpoint', 'type', 'scope', 'server_id'])
            ->allowedSorts(['id', 'name', 'type', 'created_at'])
            ->paginate($request->perPage());

        return $this->fractal->collection($webhooks)
            ->transformWith($this->getTransformer(WebhookConfigurationTransformer::class))
            ->toArray();
    }

    /**
     * View webhook
     *
     * Return data for a single webhook.
     *
     * @return array<array-key, mixed>
     */
    public function view(GetWebhookRequest $request, WebhookConfiguration $webhook): array
    {
        return $this->fractal->item($webhook)
            ->transformWith($this->getTransformer(WebhookConfigurationTransformer::class))
            ->toArray();
    }

    /**
     * Create webhook
     *
     * Create a new webhook on the Panel. Returns the created webhook and an HTTP/201
     * status response on success.
     *
     * @throws Throwable
     */
    public function store(StoreWebhookRequest $request): JsonResponse
    {
        $webhook = new WebhookConfiguration();
        $webhook->fill($request->resolvedAttributes())->saveOrFail();

        return $this->fractal->item($webhook->fresh())
            ->transformWith($this->getTransformer(WebhookConfigurationTransformer::class))
            ->addMeta([
                'resource' => route('api.application.webhooks.view', [
                    'webhook' => $webhook->id,
                ]),
            ])
            ->respond(201);
    }

    /**
     * Update webhook
     *
     * Update an existing webhook on the Panel. Only the fields sent are changed.
     *
     * @return array<array-key, mixed>
     *
     * @throws Throwable
     */
    public function update(UpdateWebhookRequest $request, WebhookConfiguration $webhook): array
    {
        $webhook->fill($request->resolvedAttributes())->saveOrFail();

        return $this->fractal->item($webhook->fresh())
            ->transformWith($this->getTransformer(WebhookConfigurationTransformer::class))
            ->toArray();
    }

    /**
     * Delete webhook
     *
     * Deletes a given webhook from the Panel.
     */
    public function delete(DeleteWebhookRequest $request, WebhookConfiguration $webhook): JsonResponse
    {
        $webhook->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Test webhook
     *
     * Fire the webhook immediately with sample data, the same way the "Test Now" button
     * in the panel does. Pass `event` and `data` to control what is sent, otherwise a
     * sample event matching the webhook's scope is used.
     *
     * The delivery is queued, so the response only confirms it was dispatched. Read the
     * result from the webhook's `deliveries` include afterwards.
     */
    public function test(TestWebhookRequest $request, WebhookConfiguration $webhook): JsonResponse
    {
        $webhook->run($request->validated('event'), $request->validated('data'));

        return new JsonResponse([
            'dispatched' => true,
            'endpoint' => $webhook->endpoint,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    /**
     * List webhook types
     *
     * Return the webhook types currently registered by the Panel and its plugins. These
     * are the accepted values for the `type` field when creating or updating a webhook.
     *
     * @return array<array-key, mixed>
     */
    public function types(GetWebhookRequest $request): array
    {
        return [
            'data' => collect(WebhookTypes::getAll())
                ->map(fn (WebhookSchemaInterface $schema) => [
                    'id' => $schema->getId(),
                    'label' => $schema->getLabel(),
                    'has_preview' => $schema->getPreviewComponent() !== null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * List webhook events
     *
     * Return the events a webhook of the given scope can subscribe to. These are the
     * accepted values for the `events` field.
     *
     * @return array<array-key, mixed>
     */
    public function events(GetWebhookRequest $request): array
    {
        $scope = $request->scope();

        return [
            'data' => collect(WebhookConfiguration::filamentCheckboxList($scope))
                ->map(fn (string $label, string $event) => [
                    'event' => $event,
                    'label' => $label,
                ])
                ->values()
                ->all(),
            'meta' => [
                'scope' => $scope->value,
            ],
        ];
    }
}
