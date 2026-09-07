<?php

namespace App\Repositories\Daemon;

use App\Enums\ContainerStatus;
use App\Enums\HttpStatusCode;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

class DaemonServerRepository extends DaemonRepository
{
    /**
     * Returns details about a server from the Daemon instance.
     *
     * @return array<string, mixed>
     */
    public function getDetails(int $timeout = 1): array
    {
        try {
            return $this->getHttpClient()->connectTimeout($timeout)->timeout($timeout)->get("/api/servers/{$this->server->uuid}")->throw()->json();
        } catch (RequestException $exception) {
            $cfId = $exception->response->header('Cf-Ray');
            $cfCache = $exception->response->header('Cf-Cache-Status');
            $code = HttpStatusCode::tryFrom($exception->getCode());

            $requestFromCloudflare = !empty($cfId);
            $requestCachedFromCloudflare = !empty($cfCache);
            $requestBadGateway = $code === HttpStatusCode::BadGateway;

            if ($requestBadGateway && $requestFromCloudflare && !$requestCachedFromCloudflare) {
                Notification::make()
                    ->title(trans('admin/node.cloudflare_issue.title'))
                    ->body(trans('admin/node.cloudflare_issue.body'))
                    ->danger()
                    ->send();
            }
        } catch (Exception $exception) {
            report($exception);
        }

        return ['state' => ContainerStatus::Missing->value];
    }

    /**
     * Creates a new server on the daemon.
     *
     * @throws ConnectionException
     */
    public function create(bool $startOnCompletion = true): void
    {
        $this->getHttpClient()->post('/api/servers', [
            'uuid' => $this->server->uuid,
            'start_on_completion' => $startOnCompletion,
        ]);
    }

    /**
     * Triggers a server sync on daemon.
     *
     * @throws ConnectionException
     */
    public function sync(): void
    {
        $this->getHttpClient()->post("/api/servers/{$this->server->uuid}/sync");
    }

    /**
     * Delete a server from the daemon, forcibly if passed.
     *
     * @throws ConnectionException
     */
    public function delete(): void
    {
        $this->getHttpClient()->delete("/api/servers/{$this->server->uuid}");
    }

    /**
     * Reinstall a server on the daemon.
     *
     * @throws ConnectionException
     */
    public function reinstall(): void
    {
        $this->getHttpClient()->post("/api/servers/{$this->server->uuid}/reinstall");
    }

    /**
     * Requests the daemon to create a full archive of the server. Once the daemon is finished
     * they will send a POST request to "/api/remote/servers/{uuid}/archive" with a boolean.
     *
     * @throws ConnectionException
     */
    public function requestArchive(): void
    {
        $this->getHttpClient()->post("/api/servers/{$this->server->uuid}/archive");
    }

    /**
     * Cancels a server transfer.
     *
     * @throws ConnectionException
     */
    public function cancelTransfer(): void
    {
        $transfer = $this->server->transfer;
        if (!$transfer) {
            return;
        }

        // Source node
        $this->setNode($transfer->oldNode);

        $this->getHttpClient()->delete("/api/servers/{$this->server->uuid}/transfer");

        // Destination node
        $this->setNode($transfer->newNode);

        $this->getHttpClient()->delete('/api/transfer', [
            'json' => [
                'server_id' => $this->server->uuid,
                'server' => [
                    'uuid' => $this->server->uuid,
                ],
            ],
        ]);
    }

    /**
     * Revokes a single user's JTI by using their ID. This is simply a helper function to
     * make it easier to revoke tokens on the fly. This ensures that the JTI key is formatted
     * correctly and avoids any costly mistakes in the codebase.
     *
     * @deprecated
     * @see self::deauthorize()
     *
     * @throws ConnectionException
     */
    public function revokeUserJTI(int $id): void
    {
        $this->getHttpClient()
            ->post("/api/servers/{$this->server->uuid}/ws/deny", [
                'jtis' => [md5($id . $this->server->uuid)],
            ]);
    }

    /**
     * Deauthorizes a user (disconnects websockets and SFTP) on the Wings instance for the server (or all servers of a node).
     *
     * @throws ConnectionException
     */
    public function deauthorize(string $user): void
    {
        $this->getHttpClient()->post('/api/deauthorize-user', [
            'json' => [
                'user' => $user,
                'servers' => $this->server ? [$this->server->uuid] : [],
            ],
        ]);
    }

    public function getInstallLogs(): string
    {
        return $this->getHttpClient()
            ->get("/api/servers/{$this->server->uuid}/install-logs")
            ->throw()
            ->json('data');
    }

    /**
     * Sends a power action to the server instance.
     *
     * @throws ConnectionException
     */
    public function power(string $action): Response
    {
        return $this->getHttpClient()->post("/api/servers/{$this->server->uuid}/power",
            ['action' => $action],
        );
    }
}
