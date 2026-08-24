<?php

namespace App\Jobs;

use App\Extensions\Webhooks\Schemas\FallbackSchema;
use App\Extensions\Webhooks\WebhookTypeService;
use App\Models\WebhookConfiguration;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Upper bound for schema requested retries. Set on the job so a released
     * delivery survives a worker running with the default --tries=1.
     */
    public int $tries = 3;

    /**
     * @param  array<mixed>  $data
     */
    public function __construct(
        private WebhookConfiguration $webhookConfiguration,
        private string $eventName,
        private array $data
    ) {}

    public function handle(WebhookTypeService $webhookTypeService): void
    {
        $data = $this->normalizeData($this->data[0] ?? []);
        $data['event'] = $this->webhookConfiguration->transformClassName($this->eventName);

        // A type without a registered schema still delivers with its stored template
        $schema = $webhookTypeService->get($this->webhookConfiguration->type) ?? new FallbackSchema();

        $payload = $schema->preparePayload($this->webhookConfiguration, $data);
        $headers = $schema->prepareHeaders($this->webhookConfiguration, $payload, $data);

        $retryAfter = null;

        try {
            // The type owns the request, so it is free to change the verb, encoding or timeout
            $response = $schema->deliver($this->webhookConfiguration, $payload, $headers);

            $successful = $schema->wasSuccessful($response) ? now() : null;

            if (!$successful) {
                report(sprintf(
                    'Webhook #%d delivery to %s failed with status %d.',
                    $this->webhookConfiguration->id,
                    $this->redactedEndpoint(),
                    $response->status(),
                ));

                $retryAfter = $schema->retryAfter($response);
            }
        } catch (Exception $exception) {
            report($exception);
            $successful = null;
        }

        $this->webhookConfiguration->webhooks()->create([
            'payload' => $payload,
            'successful_at' => $successful,
            'event' => $this->eventName,
            'endpoint' => $this->webhookConfiguration->endpoint,
        ]);

        // Only types that ask for it are retried, so default behaviour is unchanged.
        // Every attempt performs a real POST, so the delivery row per attempt above
        // is an audit trail, not a duplicate.
        if ($retryAfter !== null && $this->attempts() < $this->tries) {
            $this->release($retryAfter);
        }
    }

    /**
     * Webhook URLs routinely embed a secret, a Discord webhook token for example, so
     * only the host is ever written to the logs.
     */
    private function redactedEndpoint(): string
    {
        return parse_url($this->webhookConfiguration->endpoint, PHP_URL_HOST) ?: 'unknown host';
    }

    /** @return array<mixed> */
    private function normalizeData(mixed $data): array
    {
        if (is_string($data)) {
            return Arr::wrap(json_decode($data, true) ?? []);
        }

        if (is_object($data)) {
            return Arr::wrap($data->toArray());
        }

        return Arr::wrap($data);
    }
}
