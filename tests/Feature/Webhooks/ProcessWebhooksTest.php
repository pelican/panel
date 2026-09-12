<?php

namespace App\Tests\Feature\Webhooks;

use App\Events\Server\Installed;
use App\Jobs\ProcessWebhook;
use App\Models\Server;
use App\Models\Webhook;
use App\Models\WebhookConfiguration;
use App\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class ProcessWebhooksTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Carbon::setTestNow();
    }

    public function test_it_sends_a_single_webhook(): void
    {
        $webhook = WebhookConfiguration::factory()->create([
            'events' => [$eventName = 'eloquent.created: '.Server::class],
        ]);

        Http::fake([$webhook->endpoint => Http::response()]);

        $data = [
            'status' => null,
            'oom_killer' => false,
            'installed_at' => null,
            'owner_id' => 1,
            'node_id' => 1,
            'allocation_id' => 1,
            'egg_id' => 1,
            'uuid' => '9ff9885f-ab79-4a6e-a53e-466a84cdb2d8',
            'uuid_short' => 'ypk27val',
            'name' => 'Delmer',
            'description' => 'Est sed quibusdam sed eos quae est. Ut similique non impedit voluptas. Aperiam repellendus impedit voluptas officiis id.',
            'skip_scripts' => false,
            'memory' => 512,
            'swap' => 0,
            'disk' => 512,
            'io' => 500,
            'cpu' => 0,
            'threads' => null,
            'startup' => '/bin/bash echo "hello world"',
            'image' => 'foo/bar:latest',
            'allocation_limit' => null,
            'database_limit' => null,
            'backup_limit' => 0,
            'created_at' => '2024-09-12T20:21:29.000000Z',
            'updated_at' => '2024-09-12T20:21:29.000000Z',
            'id' => 1,
        ];

        ProcessWebhook::dispatchSync(
            $webhook,
            'eloquent.created: '.Server::class,
            [$data],
        );

        $this->assertCount(1, cache()->get("webhooks.$eventName"));
        $this->assertEquals($webhook->id, cache()->get("webhooks.$eventName")->first()->id);

        $expected = $data;
        $expected['event'] = WebhookConfiguration::transformClassName($eventName);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($webhook, $expected) {
            return $webhook->endpoint === $request->url()
                && $request->data() === $expected;
        });
    }

    public function test_sends_multiple_webhooks(): void
    {
        [$webhook1, $webhook2] = WebhookConfiguration::factory(2)
            ->create(['events' => [$eventName = 'eloquent.created: '.Server::class]]);

        Http::fake([
            $webhook1->endpoint => Http::response(),
            $webhook2->endpoint => Http::response(),
        ]);

        $this->createServer();

        $this->assertCount(2, cache()->get("webhooks.$eventName"));
        $this->assertContains($webhook1->id, cache()->get("webhooks.$eventName")->pluck('id'));
        $this->assertContains($webhook2->id, cache()->get("webhooks.$eventName")->pluck('id'));

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $webhook1->endpoint === $request->url());
        Http::assertSent(fn (Request $request) => $webhook2->endpoint === $request->url());
    }

    public function test_it_sends_no_webhooks(): void
    {
        Http::fake();

        WebhookConfiguration::factory()->create();

        $this->createServer();

        Http::assertSentCount(0);
    }

    public function test_it_sends_some_webhooks(): void
    {
        [$webhook1, $webhook2] = WebhookConfiguration::factory(2)
            ->sequence(
                ['events' => ['eloquent.created: '.Server::class]],
                ['events' => ['eloquent.deleted: '.Server::class]]
            )->create();

        Http::fake([
            $webhook1->endpoint => Http::response(),
            $webhook2->endpoint => Http::response(),
        ]);

        $this->createServer();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $webhook1->endpoint === $request->url());
        Http::assertNotSent(fn (Request $request) => $webhook2->endpoint === $request->url());
    }

    public function test_it_records_when_a_webhook_is_sent(): void
    {
        $webhookConfig = WebhookConfiguration::factory()
            ->create(['events' => ['eloquent.created: '.Server::class]]);

        Http::fake([$webhookConfig->endpoint => Http::response()]);

        $this->assertDatabaseCount(Webhook::class, 0);

        $server = $this->createServer();

        $this->assertDatabaseCount(Webhook::class, 1);

        $webhook = Webhook::query()->first();
        $this->assertEquals($server->uuid, $webhook->payload['data']['uuid']);

        $this->assertDatabaseHas(Webhook::class, [
            'endpoint' => $webhookConfig->endpoint,
            'successful_at' => now()->startOfSecond(),
            'event' => 'eloquent.created: '.Server::class,
        ]);
    }

    public function test_it_records_when_a_webhook_fails(): void
    {
        $webhookConfig = WebhookConfiguration::factory()->create([
            'events' => ['eloquent.created: '.Server::class],
        ]);

        Http::fake([$webhookConfig->endpoint => Http::response(status: 500)]);

        $this->assertDatabaseCount(Webhook::class, 0);

        $server = $this->createServer();

        $this->assertDatabaseCount(Webhook::class, 1);
        $this->assertEquals($server->uuid, Webhook::query()->first()->payload['data']['uuid']);
        $this->assertDatabaseHas(Webhook::class, [
            'endpoint' => $webhookConfig->endpoint,
            'successful_at' => null,
            'event' => 'eloquent.created: '.Server::class,
        ]);
    }

    public function test_it_is_triggered_on_custom_events(): void
    {
        $webhookConfig = WebhookConfiguration::factory()->create([
            'events' => [Installed::class],
        ]);

        Http::fake([$webhookConfig->endpoint => Http::response()]);

        $this->assertDatabaseCount(Webhook::class, 0);

        $server = $this->createServer();

        event(new Installed($server, true, true));

        $this->assertDatabaseCount(Webhook::class, 1);
        $this->assertDatabaseHas(Webhook::class, [
            // 'payload' => json_encode([['server' => $server->toArray()]]),
            'endpoint' => $webhookConfig->endpoint,
            'successful_at' => now()->startOfSecond(),
            'event' => Installed::class,
        ]);

    }

    public function createServer(): Server
    {
        return Server::factory()->withNode()->create();
    }
}
