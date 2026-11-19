<?php

namespace App\Http\Controllers\Api\Remote\Backups;

use App\Events\Server\BackupCompleted;
use App\Exceptions\Http\HttpForbiddenException;
use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Extensions\BackupAdapter\Schemas\S3BackupSchema;
use App\Facades\Activity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Remote\ReportBackupCompleteRequest;
use App\Models\Backup;
use App\Models\Node;
use App\Models\Server;
use App\Services\Backups\ServerRestoreService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

class BackupStatusController extends Controller
{
    /**
     * BackupStatusController constructor.
     */
    public function __construct(private BackupAdapterService $backupService, private ServerRestoreService $restoreService) {}

    /**
     * Handles updating the state of a backup.
     *
     * @throws Throwable
     */
    public function index(ReportBackupCompleteRequest $request, string $backup): JsonResponse
    {
        // Get the node associated with the request.
        /** @var Node $node */
        $node = $request->attributes->get('node');

        /** @var Backup $model */
        $model = Backup::query()
            ->where('uuid', $backup)
            ->firstOrFail();

        // Check that the backup is "owned" by the node making the request. This avoids other nodes
        // from messing with backups that they don't own.
        /** @var Server $server */
        $server = $model->server;
        throw_if($server->node_id !== $node->id, new HttpForbiddenException('Requesting node does not have permission to access this server.'));

        throw_if($model->is_successful, new BadRequestHttpException('Cannot update the status of a backup that is already marked as completed.'));

        $action = $request->boolean('successful') ? 'server:backup.complete' : 'server:backup.fail';
        $log = Activity::event($action)->subject($model, $model->server)->property('name', $model->name);

        $log->transaction(function () use ($model, $request) {
            $successful = $request->boolean('successful');

            $model->fill([
                'is_successful' => $successful,
                // Change the lock state to unlocked if this was a failed backup so that it can be
                // deleted easily. Also does not make sense to have a locked backup on the system
                // that is failed.
                'is_locked' => $successful ? $model->is_locked : false,
                'checksum' => $successful ? ($request->input('checksum_type') . ':' . $request->input('checksum')) : null,
                'bytes' => $successful ? $request->input('size') : 0,
                'completed_at' => CarbonImmutable::now(),
            ])->save();

            // Check if we are using the s3 backup adapter. If so, make sure we mark the backup as
            // being completed in S3 correctly.
            $schema = $this->backupService->get($model->backupHost->schema);
            if ($schema instanceof S3BackupSchema) {
                $schema->completeMultipartUpload($model, $successful, $request->input('parts'));
            }
        });

        // Keep the server configuration next to the archive in the bucket, so the
        // server can be restored from it without this Panel's record.
        $schema = $this->backupService->get($model->backupHost->schema);
        if ($schema instanceof S3BackupSchema && $model->is_successful) {
            try {
                $schema->storeConfig($model);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        event(new BackupCompleted($model));

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Handles toggling the restoration status of a server. The server status field should be
     * set back to null, even if the restoration failed. This is not an unsolvable state for
     * the server, and the user can keep trying to restore, or just use the reinstall button.
     *
     * The only thing the successful field does is update the entry value for the audit logs
     * table tracking for this restoration.
     *
     * @throws Throwable
     */
    public function restore(Request $request, string $backup): JsonResponse
    {
        /** @var Node $node */
        $node = $request->attributes->get('node');

        // A backup restored into a new server created from it.
        $restore = $this->restoreService->findRunning($backup, $node);
        if ($restore) {
            $this->restoreService->complete($restore, $request->boolean('successful'));

            return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
        }

        /** @var Backup $model */
        $model = Backup::query()->where('uuid', $backup)->firstOrFail();

        throw_unless($model->server?->node->is($node), new HttpForbiddenException('Requesting node does not have permission to access this server.'));

        $model->server->update(['status' => null]);

        Activity::event($request->boolean('successful') ? 'server:backup.restore-complete' : 'server:backup.restore-failed')
            ->subject($model, $model->server)
            ->property('name', $model->name)
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
