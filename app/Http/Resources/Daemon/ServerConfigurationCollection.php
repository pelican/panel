<?php

namespace App\Http\Resources\Daemon;

use App\Models\Server;
use App\Services\Eggs\EggConfigurationService;
use App\Services\Servers\ServerConfigurationStructureService;
use Illuminate\Container\Container;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ServerConfigurationCollection extends ResourceCollection
{
    /**
     * Converts a collection of Server models into an array of configuration responses
     * that can be understood by daemon. Make sure you've properly loaded the required
     * relationships on the Server models before calling this function, otherwise you'll
     * have some serious performance issues from all the N+1 queries.
     *
     * @return array<array{uuid: string, }>
     */
    public function toArray($request): array
    {
        /** @var EggConfigurationService $egg */
        $egg = Container::getInstance()->make(EggConfigurationService::class);

        /** @var ServerConfigurationStructureService $configuration */
        $configuration = Container::getInstance()->make(ServerConfigurationStructureService::class);

        return $this->collection->map(function (Server $server) use ($configuration, $egg) {
            $settings = $configuration->handle($server);

            return [
                'uuid' => $server->uuid,
                'settings' => $settings,
                'process_configuration' => $egg->handle($server, $settings),
            ];
        })->toArray();
    }
}
