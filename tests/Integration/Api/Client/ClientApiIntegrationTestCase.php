<?php

namespace App\Tests\Integration\Api\Client;

use App\Data\Api\ApiResource;
use App\Models\Allocation;
use App\Models\Backup;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\Task;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class ClientApiIntegrationTestCase extends IntegrationTestCase
{
    /**
     * Override the default createTestResponse from Illuminate so that we can
     * just dump 500-level errors to the screen in the tests without having
     * to keep re-assigning variables.
    protected function createTestResponse($response, $request): \Illuminate\Testing\TestResponse
    {
        return TestResponse::fromBaseResponse($response);
    }

    /**
     * Returns a link to the specific resource using the client API.
     */
    protected function link(mixed $model, ?string $append = null): string
    {
        switch (get_class($model)) {
            case Server::class:
                $link = "/api/client/servers/$model->uuid";
                break;
            case Schedule::class:
                $link = "/api/client/servers/{$model->server->uuid}/schedules/$model->id";
                break;
            case Task::class:
                $link = "/api/client/servers/{$model->schedule->server->uuid}/schedules/{$model->schedule->id}/tasks/$model->id";
                break;
            case Allocation::class:
                $link = "/api/client/servers/{$model->server->uuid}/network/allocations/$model->id";
                break;
            case Backup::class:
                $link = "/api/client/servers/{$model->server->uuid}/backups/$model->uuid";
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Cannot create link for Model of type %s', class_basename($model)));
        }

        return $link . ($append ? '/' . ltrim($append, '/') : '');
    }

    /**
     * Asserts that the data passed through matches the attributes the matching client
     * Data class produces for the model, ignoring the "relationships" key.
     */
    protected function assertJsonTransformedWith(array $data, Model $model): void
    {
        $dataClass = sprintf('\\App\\Data\\Api\\Client\\%sData', class_basename($model));
        $this->assertTrue(is_a($dataClass, ApiResource::class, true));

        $expected = Container::getInstance()->call([$dataClass, 'fromModel'], ['model' => $model])->toArray();

        $this->assertSame(
            $expected,
            Collection::make($data)->except(['relationships'])->toArray()
        );
    }
}
