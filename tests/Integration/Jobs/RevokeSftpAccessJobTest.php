<?php

namespace App\Tests\Integration\Jobs;

use App\Jobs\RevokeSftpAccessJob;
use App\Models\Node;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;

class RevokeSftpAccessJobTest extends IntegrationTestCase
{
    #[TestWith([Server::class, 'server'])]
    #[TestWith([Node::class, 'node'])]
    public function test_unique_id_based_on_model_type(string $class, string $key): void
    {
        $model = $class::factory()->make(['uuid' => 'uuid-1234']);

        $job = new RevokeSftpAccessJob('user-1', $model);

        $this->assertEquals(
            "revoke-sftp:user-1:{$key}:uuid-1234",
            $job->uniqueId()
        );
    }

    public function test_job_releases_back_to_queue_on_failure(): void
    {
        $node = Node::factory()->make(['uuid' => 'uuid-1234']);

        $mock = $this->mock(DaemonServerRepository::class, function ($mock) {
            $mock->expects('setNode')->andReturnSelf();
            $mock->expects('deauthorize')->andThrows(new ConnectionException());
        });

        $job = \Mockery::mock(RevokeSftpAccessJob::class, ['user-1', $node])->makePartial();
        $job->expects('release')->with(10);

        $job->handle($mock);
    }

    public function test_job_dispatches_for_node(): void
    {
        $node = Node::factory()->make(['uuid' => 'uuid-1234']);

        $mock = $this->mock(DaemonServerRepository::class, function ($mock) {
            $mock->expects('setNode')->andReturnSelf();
            $mock->expects('deauthorize')->with('user-1')->andReturnUndefined();
        });

        (new RevokeSftpAccessJob('user-1', $node))->handle($mock);
    }

    public function test_job_dispatches_for_individual_server(): void
    {
        $node = Node::factory()->make(['uuid' => 'node-1234']);
        $server = Server::factory()->make(['uuid' => 'server-1234'])->setRelation('node', $node);

        $mock = $this->mock(DaemonServerRepository::class, function ($mock) {
            $mock->expects('setServer')->with(\Mockery::on(fn (Server $server) => $server->uuid === 'server-1234'))->andReturnSelf();
            $mock->expects('deauthorize')->with('user-1')->andReturnUndefined();
        });

        (new RevokeSftpAccessJob('user-1', $server))->handle($mock);
    }

    public function test_deauthorize_sends_request_for_node(): void
    {
        Http::fake();

        $node = Node::factory()->make(['uuid' => 'node-1234']);

        (new RevokeSftpAccessJob('user-1', $node))->handle(app(DaemonServerRepository::class));

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/deauthorize-user')
                && $request['user'] === 'user-1'
                && $request['servers'] === [];
        });
    }

    public function test_deauthorize_sends_request_for_server(): void
    {
        Http::fake();

        $node = Node::factory()->make(['uuid' => 'node-1234']);
        $server = Server::factory()->make(['uuid' => 'server-1234'])->setRelation('node', $node);

        (new RevokeSftpAccessJob('user-1', $server))->handle(app(DaemonServerRepository::class));

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/deauthorize-user')
                && $request['user'] === 'user-1'
                && $request['servers'] === ['server-1234'];
        });
    }
}
