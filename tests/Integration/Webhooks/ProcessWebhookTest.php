<?php

namespace App\Tests\Integration\Webhooks;

use App\Extensions\Webhooks\Schemas\BaseSchema;
use App\Extensions\Webhooks\WebhookTypeService;
use App\Jobs\ProcessWebhook;
use App\Models\Server;
use App\Models\Webhook;
use App\Models\WebhookConfiguration;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Covers the job's data normalization and the fallback delivery path used when a
 * webhook's type has no registered schema.
 */
class ProcessWebhookTest extends IntegrationTestCase
{
    private const EVENT = 'eloquent.created: ' . Server::class;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /** @param array<string, mixed> $attributes */
    private function webhook(array $attributes = []): WebhookConfiguration
    {
        return WebhookConfiguration::factory()->create(array_merge([
            'events' => [self::EVENT],
        ], $attributes));
    }

    public function test_a_scalar_data_element_does_not_crash_the_job(): void
    {
        $webhook = $this->webhook();

        Http::fake([$webhook->endpoint => Http::response()]);

        ProcessWebhook::dispatchSync($webhook, self::EVENT, ['hello']);

        Http::assertSentCount(1);
        $this->assertNotNull(Webhook::query()->latest('id')->first()->successful_at);
    }

    public function test_a_single_key_payload_is_delivered_intact(): void
    {
        $webhook = $this->webhook();

        Http::fake([$webhook->endpoint => Http::response()]);

        ProcessWebhook::dispatchSync($webhook, self::EVENT, [['name' => 'Example']]);

        Http::assertSent(fn (Request $request) => $request['name'] === 'Example');
    }

    public function test_an_unregistered_type_still_uses_stored_headers_payload_and_timeout(): void
    {
        config()->set('panel.webhook.timeout', 5);

        $webhook = $this->webhook([
            'type' => 'ghost',
            'headers' => ['X-Foo' => 'bar'],
            'payload' => ['msg' => '{{name}}'],
        ]);

        Http::fake([$webhook->endpoint => Http::response()]);

        ProcessWebhook::dispatchSync($webhook, self::EVENT, [['name' => 'Example']]);

        Http::assertSent(fn (Request $request) => $request->header('X-Foo')[0] === 'bar'
            && $request['msg'] === 'Example');
        $this->assertNotNull(Webhook::query()->latest('id')->first()->successful_at);
    }

    public function test_a_schema_requested_retry_releases_the_job(): void
    {
        $this->app->make(WebhookTypeService::class)->register(new RetryingSchema());
        $webhook = $this->webhook(['type' => 'retrying']);

        Http::fake([$webhook->endpoint => Http::response([], 429)]);

        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(10);

        $job = new ProcessWebhook($webhook, self::EVENT, [['name' => 'Example']]);
        $job->setJob($queueJob);

        $this->app->call([$job, 'handle']);

        $this->assertNull(Webhook::query()->latest('id')->first()->successful_at);
    }

    public function test_the_final_attempt_is_not_released_again(): void
    {
        $this->app->make(WebhookTypeService::class)->register(new RetryingSchema());
        $webhook = $this->webhook(['type' => 'retrying']);

        Http::fake([$webhook->endpoint => Http::response([], 429)]);

        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(3);
        $queueJob->shouldNotReceive('release');

        $job = new ProcessWebhook($webhook, self::EVENT, [['name' => 'Example']]);
        $job->setJob($queueJob);

        $this->app->call([$job, 'handle']);

        $this->assertDatabaseCount(Webhook::class, 1);
    }
}

class RetryingSchema extends BaseSchema
{
    public function getId(): string
    {
        return 'retrying';
    }

    public function retryAfter(Response $response): ?int
    {
        return $response->status() === 429 ? 10 : null;
    }
}
