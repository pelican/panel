<?php

namespace App\Http\Controllers\Api\Remote\Servers;

use App\Exceptions\Http\HttpForbiddenException;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;
use Webmozart\Assert\Assert;

class ServerTransferController extends Controller
{
    /**
     * ServerTransferController constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private DaemonServerRepository $daemonServerRepository,
    ) {}

    /**
     * The daemon notifies us about a transfer failure.
     *
     * @throws Throwable
     */
    public function failure(Request $request, Server $server): JsonResponse
    {
        $transfer = $server->transfer;
        throw_if(is_null($transfer), new ConflictHttpException('Server is not being transferred.'));

        /* @var Node $node */
        Assert::isInstanceOf($node = $request->attributes->get('node'), Node::class);

        // Either node can tell the panel that the transfer has failed. Only the new node
        // can tell the panel that it was successful.
        throw_if(!$node->is($transfer->newNode) && !$node->is($transfer->oldNode), new HttpForbiddenException('Requesting node does not have permission to access this server.'));

        $this->connection->transaction(function () use ($transfer) {
            $transfer->forceFill(['successful' => false])->saveOrFail();

            if ($transfer->new_allocation || $transfer->new_additional_allocations) {
                $allocations = array_merge([$transfer->new_allocation], $transfer->new_additional_allocations);
                Allocation::query()->whereIn('id', $allocations)->update(Allocation::RELEASE_ATTRIBUTES);
            }
        });

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * The daemon notifies us about a transfer success.
     *
     * @throws Throwable
     */
    public function success(Request $request, Server $server): JsonResponse
    {
        $transfer = $server->transfer;
        throw_if(is_null($transfer), new ConflictHttpException('Server is not being transferred.'));

        /* @var Node $node */
        Assert::isInstanceOf($node = $request->attributes->get('node'), Node::class);

        // Only the new node communicates a successful state to the panel, so we should
        // not allow the old node to hit this endpoint.
        throw_unless($node->is($transfer->newNode), new HttpForbiddenException('Requesting node does not have permission to access this server.'));

        /** @var Server $server */
        $server = $this->connection->transaction(function () use ($server, $transfer) {
            $data = [];

            if ($transfer->old_allocation || $transfer->old_additional_allocations) {
                $allocations = array_merge([$transfer->old_allocation], $transfer->old_additional_allocations);
                // Remove the old allocations for the server and re-assign the server to the new
                // primary allocation and node.
                Allocation::query()->whereIn('id', $allocations)->update(Allocation::RELEASE_ATTRIBUTES);
                $data['allocation_id'] = $transfer->new_allocation;
            }

            $data['node_id'] = $transfer->new_node;
            $server->update($data);

            $server = $server->fresh();
            $server->transfer->update(['successful' => true]);

            return $server;
        });

        // Delete the server from the old node making sure to point it to the old node so
        // that we do not delete it from the new node the server was transferred to.
        try {
            $this->daemonServerRepository
                ->setServer($server)
                ->setNode($transfer->oldNode)
                ->delete();
        } catch (ConnectionException $exception) {
            logger()->warning($exception, ['transfer_id' => $server->transfer->id]);
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
