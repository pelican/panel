<?php

namespace App\Services\Servers;

use App\Jobs\RevokeSftpAccessJob;
use App\Models\Server;
use App\Traits\Services\ReturnsUpdatedModels;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Throwable;

class DetailsModificationService
{
    use ReturnsUpdatedModels;

    /**
     * DetailsModificationService constructor.
     */
    public function __construct(private ConnectionInterface $connection) {}

    /**
     * Update the details for a single server instance.
     *
     * @param array{
     *     external_id: int,
     *     owner_id: int,
     *     name: string,
     *     description?: ?string
     * } $data
     *
     * @throws Throwable
     */
    public function handle(Server $server, array $data): Server
    {
        return $this->connection->transaction(function () use ($data, $server) {
            $oldOwner = $server->user;

            $server->forceFill([
                'external_id' => Arr::get($data, 'external_id'),
                'owner_id' => Arr::get($data, 'owner_id'),
                'name' => Arr::get($data, 'name'),
                'description' => Arr::get($data, 'description') ?? '',
            ])->saveOrFail();

            // If the owner_id value is changed we need to revoke any tokens that exist for the server
            // on the daemon instance so that the old owner no longer has any permission to access the
            // websockets.
            if ($server->owner_id !== $oldOwner->id) {
                RevokeSftpAccessJob::dispatch($oldOwner->uuid, $server);
            }

            return $server;
        });
    }
}
