<?php

namespace App\Extensions\BackupAdapter\Schemas;

use App\Enums\NodeJwtScope;
use App\Models\Backup;
use App\Models\User;
use App\Repositories\Daemon\DaemonBackupRepository;
use App\Services\Nodes\NodeJWTService;
use Carbon\CarbonImmutable;
use Exception;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Illuminate\Http\Response;
use Webmozart\Assert\Assert;

final class WingsBackupSchema extends BackupAdapterSchema
{
    public function __construct(private readonly DaemonBackupRepository $repository, private readonly NodeJWTService $jwtService) {}

    public function getId(): string
    {
        return 'wings';
    }

    public function createBackup(Backup $backup): void
    {
        $this->repository->setServer($backup->server)->create($backup);
    }

    /** @throws Exception */
    public function deleteBackup(Backup $backup): void
    {
        try {
            if ($backup->server) {
                try {
                    $this->repository->setServer($backup->server)->delete($backup);

                    return;
                } catch (Exception $exception) {
                    // The server may already be gone from the Daemon.
                    if ($exception->getCode() !== Response::HTTP_NOT_FOUND || !$backup->server_uuid) {
                        throw $exception;
                    }
                }
            }

            $node = $backup->sourceNode();
            if ($node && $backup->server_uuid) {
                // The server was deleted and this backup kept, so it is no longer
                // known to the Daemon by its server.
                $this->repository->setNode($node)->deleteKept($backup);
            }
        } catch (Exception $exception) {
            // Don't fail the request if the Daemon responds with a 404, just assume the backup
            // doesn't actually exist and remove its reference from the Panel as well.
            if ($exception->getCode() !== Response::HTTP_NOT_FOUND) {
                throw $exception;
            }
        }
    }

    public function getDownloadLink(Backup $backup, User $user): string
    {
        return $this->createLink($backup, $user, CarbonImmutable::now()->addMinutes(15));
    }

    /**
     * A link another node can use to download this backup when restoring it
     * into a new server.
     */
    public function getRestoreLink(Backup $backup, int $minutes = 60): string
    {
        return $this->createLink($backup, null, CarbonImmutable::now()->addMinutes($minutes));
    }

    private function createLink(Backup $backup, ?User $user, CarbonImmutable $expiresAt): string
    {
        $node = $backup->sourceNode();
        $serverUuid = $backup->sourceServerUuid();

        Assert::notNull($node, 'This backup is not stored on any node.');
        Assert::notNull($serverUuid, 'This backup has no server.');

        $service = $this->jwtService
            ->setExpiresAt($expiresAt)
            ->setScopes(NodeJwtScope::BackupDownload)
            ->setClaims([
                'backup_uuid' => $backup->uuid,
                'server_uuid' => $serverUuid,
            ]);

        if ($user) {
            $service->setUser($user);
        }

        $token = $service->handle($node, ($user->id ?? 'restore') . $serverUuid);

        return $node->getConnectionAddress() . '/download/backup?token=' . $token->toString();
    }

    /** @return Component[] */
    public function getConfigurationForm(): array
    {
        return [
            TextEntry::make(trans('admin/backuphost.no_configuration')),
        ];
    }
}
