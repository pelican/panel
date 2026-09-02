<?php

namespace App\Tests\Integration\Services\Servers;

use App\Exceptions\DisplayException;
use App\Jobs\RevokeSftpAccessJob;
use App\Models\User;
use App\Services\Servers\DetailsModificationService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Bus;

class DetailsModificationServiceTest extends IntegrationTestCase
{
    public function test_owner_is_rechecked_inside_the_update_transaction(): void
    {
        Bus::fake([RevokeSftpAccessJob::class]);
        $server = $this->createServerModel();
        $destination = User::factory()->create();
        $connection = \Mockery::mock(ConnectionInterface::class);
        $connection->expects('transaction')->once()->andReturnUsing(function (callable $callback) use ($destination) {
            $destination->forceFill(['suspended_at' => now()])->save();

            return $callback();
        });

        $service = new DetailsModificationService($connection);

        try {
            $service->handle($server, [
                'external_id' => 123,
                'owner_id' => $destination->id,
                'name' => 'Updated server',
                'description' => 'Updated description',
            ]);
            $this->fail('The suspended owner should be rejected inside the transaction.');
        } catch (DisplayException $exception) {
            $this->assertSame('Servers cannot be assigned to a suspended account.', $exception->getMessage());
        }

        $this->assertNotSame($destination->id, $server->refresh()->owner_id);
    }

    public function test_active_owner_can_be_assigned(): void
    {
        Bus::fake([RevokeSftpAccessJob::class]);
        $server = $this->createServerModel();
        $destination = User::factory()->create();

        $updated = $this->app->make(DetailsModificationService::class)->handle($server, [
            'external_id' => 123,
            'owner_id' => $destination->id,
            'name' => 'Updated server',
            'description' => 'Updated description',
        ]);

        $this->assertSame($destination->id, $updated->owner_id);
        $this->assertSame('Updated server', $updated->name);
        Bus::assertDispatched(RevokeSftpAccessJob::class);
    }
}
