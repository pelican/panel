<?php

namespace App\Tests\Integration\Jobs;

use App\Jobs\SendServerPowerAction;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class SendServerPowerActionTest extends IntegrationTestCase
{
    public function test_a_refused_power_action_fails_the_job_so_it_is_retried(): void
    {
        Http::fake(['*/power' => Http::response(status: 409, headers: ['User-Agent' => 'Pelican Wings'])]);

        $this->expectException(RequestException::class);

        (new SendServerPowerAction($this->createServerModel(), 'start'))->handle($this->app->make(DaemonServerRepository::class));
    }

    public function test_an_accepted_power_action_completes_the_job(): void
    {
        Http::fake(['*/power' => Http::response(status: 204)]);

        (new SendServerPowerAction($this->createServerModel(), 'start'))->handle($this->app->make(DaemonServerRepository::class));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/power') && $request['action'] === 'start');
    }
}
