<?php

namespace App\Http\Controllers\Api\Remote\Backups;

use App\Exceptions\Http\HttpForbiddenException;
use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Extensions\BackupAdapter\Schemas\S3BackupSchema;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\Node;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BackupRemoteUploadController extends Controller
{
    public const MIN_MAX_PART_SIZE = 5 * 1024 * 1024; // 5 MiB

    public const DEFAULT_MAX_PART_SIZE = 128 * 1024 * 1024; // 128 MiB

    public const MAX_MAX_PART_SIZE = 5 * 1024 * 1024 * 1024; // 5 GiB

    public const MAX_CONCURRENT_UPLOADS = 10;

    /**
     * BackupRemoteUploadController constructor.
     */
    public function __construct(private BackupAdapterService $backupService) {}

    /**
     * Returns the required presigned urls to upload a backup to S3 cloud storage.
     *
     * @throws BadRequestHttpException
     * @throws ModelNotFoundException
     * @throws HttpForbiddenException
     * @throws ConflictHttpException
     */
    public function __invoke(Request $request, string $backup): JsonResponse
    {
        /** @var Node $node */
        $node = $request->attributes->get('node');

        $size = (int) $request->query('size');
        throw_if(empty($size), new BadRequestHttpException('A non-empty "size" query parameter must be provided.'));

        $backup = Backup::where('uuid', $backup)->firstOrFail();

        // Check that the backup is "owned" by the node making the request. This avoids other nodes
        // from messing with backups that they don't own.
        if ($backup->server->node_id !== $node->id) {
            throw new HttpForbiddenException('Requesting node does not have permission to access this server.');
        }

        // Prevent backups that have already been completed from trying to be uploaded again.
        if (!is_null($backup->completed_at)) {
            throw new ConflictHttpException('This backup is already in a completed state.');
        }

        // Ensure we are using the S3 schema.
        $schema = $this->backupService->get($backup->backupHost->schema);
        if (!$schema instanceof S3BackupSchema) {
            throw new BadRequestHttpException('The configured backup schema is not an S3 compatible.');
        }

        return new JsonResponse($schema->getUploadParts($backup, $size));
    }
}
