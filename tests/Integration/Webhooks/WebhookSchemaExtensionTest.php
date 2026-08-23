<?php

namespace App\Tests\Integration\Webhooks;

use App\Enums\WebhookScope;
use App\Extensions\Webhooks\Schemas\BaseSchema;
use App\Extensions\Webhooks\WebhookTypeService;
use App\Jobs\ProcessWebhook;
use App\Models\Server;
use App\Models\Webhook;
use App\Models\WebhookConfiguration;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Covers the extension points a webhook type can use beyond shaping its payload:
 * signing, transport, response interpretation, validation and event discovery.
 */
class WebhookSchemaExtensionTest extends IntegrationTestCase
{
    private WebhookTypeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(WebhookTypeService::class);
        Http::preventStrayRequests();
    }

    private function webhook(string $type): WebhookConfiguration
    {
        return WebhookConfiguration::factory()->create([
            'type' => $type,
            'events' => ['eloquent.created: ' . Server::class],
        ]);
    }

    private function dispatch(WebhookConfiguration $webhook): void
    {
        ProcessWebhook::dispatchSync($webhook, 'eloquent.created: ' . Server::class, [['name' => 'Example']]);
    }

    public function test_a_type_can_sign_the_exact_body_it_sends(): void
    {
        $this->service->register(new SigningSchema());
        $webhook = $this->webhook('signing');

        Http::fake([$webhook->endpoint => Http::response()]);

        $this->dispatch($webhook);

        Http::assertSent(function (Request $request) {
            $expected = 'sha256=' . hash_hmac('sha256', $request->body(), SigningSchema::SECRET);

            return $request->header('X-Hub-Signature-256')[0] === $expected;
        });
    }

    public function test_a_type_can_replace_the_transport(): void
    {
        $this->service->register(new FormPutSchema());
        $webhook = $this->webhook('formput');

        Http::fake([$webhook->endpoint => Http::response()]);

        $this->dispatch($webhook);

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && str_contains($request->header('Content-Type')[0], 'application/x-www-form-urlencoded'));
    }

    public function test_a_type_decides_what_counts_as_a_successful_delivery(): void
    {
        $this->service->register(new BodySuccessSchema());
        $webhook = $this->webhook('bodysuccess');

        // A 2xx that the endpoint uses to report a failure in the body
        Http::fake([$webhook->endpoint => Http::response(['ok' => false], 200)]);

        $this->dispatch($webhook);

        $this->assertNull(Webhook::query()->latest('id')->first()->successful_at);
    }

    public function test_a_successful_body_is_recorded_as_delivered(): void
    {
        $this->service->register(new BodySuccessSchema());
        $webhook = $this->webhook('bodysuccess');

        Http::fake([$webhook->endpoint => Http::response(['ok' => true], 200)]);

        $this->dispatch($webhook);

        $this->assertNotNull(Webhook::query()->latest('id')->first()->successful_at);
    }

    public function test_the_built_in_type_still_sends_a_json_post(): void
    {
        $webhook = $this->webhook(WebhookTypeService::Default);

        Http::fake([$webhook->endpoint => Http::response()]);

        $this->dispatch($webhook);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_contains($request->header('Content-Type')[0], 'application/json'));
    }

    public function test_a_type_can_declare_rules_for_its_payload(): void
    {
        $schema = new BodySuccessSchema();

        $this->assertSame(['note' => ['string', 'max:5']], $schema->getPayloadRules());
    }

    public function test_a_plugin_can_add_its_own_events(): void
    {
        $pluginEvent = 'eloquent.created: Pelican\\SomePlugin\\Models\\Thing';

        Event::listen('global:webhook.events', function (array &$events) use ($pluginEvent) {
            $events[] = $pluginEvent;
        });

        $this->assertArrayHasKey(
            $pluginEvent,
            WebhookConfiguration::filamentCheckboxList(WebhookScope::Global)
        );
    }
}

class SigningSchema extends BaseSchema
{
    public const SECRET = 'topsecret';

    public function getId(): string
    {
        return 'signing';
    }

    public function prepareHeaders(WebhookConfiguration $webhookConfiguration, array $payload, array $eventData): array
    {
        return ['X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', json_encode($payload), self::SECRET)];
    }
}

class FormPutSchema extends BaseSchema
{
    public function getId(): string
    {
        return 'formput';
    }

    public function deliver(WebhookConfiguration $webhookConfiguration, array $payload, array $headers): Response
    {
        return Http::withHeaders($headers)->asForm()->timeout(5)->put($webhookConfiguration->endpoint, $payload);
    }
}

class BodySuccessSchema extends BaseSchema
{
    public function getId(): string
    {
        return 'bodysuccess';
    }

    public function getPayloadRules(): array
    {
        return ['note' => ['string', 'max:5']];
    }

    public function wasSuccessful(Response $response): bool
    {
        return $response->json('ok') === true;
    }
}
