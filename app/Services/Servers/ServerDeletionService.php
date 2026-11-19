<?php

namespace App\Services\Servers;

use App\Exceptions\DisplayException;
use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Extensions\BackupAdapter\Schemas\WingsBackupSchema;
use App\Models\Allocation;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Databases\DatabaseManagementService;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Response;
use Throwable;

class ServerDeletionService
{
    protected bool $force = false;

    protected bool $keepBackups = false;

    /**
     * ServerDeletionService constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private DaemonServerRepository $daemonServerRepository,
        private DatabaseManagementService $databaseManagementService,
        private BackupAdapterService $backupService
    ) {}

    /**
     * Set if the server should be forcibly deleted from the panel (ignoring daemon errors) or not.
     */
    public function withForce(bool $bool = true): self
    {
        $this->force = $bool;

        return $this;
    }

    /**
     * Set if the server's finished backups should be kept so that it can be
     * restored from them later.
     */
    public function keepBackups(bool $bool = true): self
    {
        $this->keepBackups = $bool;

        return $this;
    }

    /**
     * Delete a server from the panel and remove any associated databases from hosts.
     *
     * @throws Throwable
     * @throws DisplayException
     */
    public function handle(Server $server): void
    {
        try {
            $repository = $this->daemonServerRepository->setServer($server);
            $this->keepBackups ? $repository->delete(true) : $repository->delete();
        } catch (ConnectionException $exception) {
            // If there is an error not caused a 404 error and this isn't a forced delete,
            // go ahead and bail out. We specifically ignore a 404 since that can be assumed
            // to be a safe error, meaning the server doesn't exist at all on daemon so there
            // is no reason we need to bail out from that.
            if (!$this->force && $exception->getCode() !== Response::HTTP_NOT_FOUND) {
                throw $exception;
            }

            logger()->warning($exception);
        } catch (Exception $exception) {
            report($exception);
        }

        $this->connection->transaction(function () use ($server) {
            foreach ($server->databases as $database) {
                try {
                    $this->databaseManagementService->delete($database);
                } catch (Exception $exception) {
                    if (!$this->force) {
                        throw $exception;
                    }

                    // Oh well, just try to delete the database entry we have from the database
                    // so that the server itself can be deleted. This will leave it dangling on
                    // the host instance, but we couldn't delete it anyways so not sure how we would
                    // handle this better anyways.
                    $database->delete();

                    logger()->warning($exception);
                }
            }

            foreach ($server->backups()->with(['backupHost', 'server.node', 'node'])->get() as $backup) {
                // Finished backups are kept, and stay in the Panel without a server.
                if ($this->keepBackups && $backup->is_successful) {
                    $backup->update(['server_id' => null]);

                    continue;
                }

                try {
                    $schema = $this->backupService->get($backup->backupHost->schema);

                    if ($schema) {
                        if ($schema instanceof WingsBackupSchema) {
                            // Local wings backups are deleted by the daemon with the server
                            if ($this->keepBackups) {
                                $schema->deleteBackup($backup);
                            }
                        } else {
                            $schema->deleteBackup($backup);
                        }
                    }
                } catch (Exception $exception) {
                    if (!$this->force) {
                        throw $exception;
                    }

                    logger()->warning($exception);
                }

                $backup->delete();
            }

            $server->allocations()->update(Allocation::RELEASE_ATTRIBUTES);

            $server->delete();
        });
    }
}
