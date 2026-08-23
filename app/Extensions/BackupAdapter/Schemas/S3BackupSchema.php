<?php

namespace App\Extensions\BackupAdapter\Schemas;

use App\Enums\TablerIcon;
use App\Http\Controllers\Api\Remote\Backups\BackupRemoteUploadController;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\User;
use App\Repositories\Daemon\DaemonBackupRepository;
use Aws\S3\S3Client;
use Carbon\CarbonImmutable;
use Exception;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\StateCasts\BooleanStateCast;
use Illuminate\Support\Arr;

final class S3BackupSchema extends BackupAdapterSchema
{
    public function __construct(private readonly DaemonBackupRepository $repository) {}

    private function createClient(BackupHost $backupHost): S3Client
    {
        $config = $backupHost->configuration;
        $config['version'] = 'latest';

        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return new S3Client($config);
    }

    public function getId(): string
    {
        return 's3';
    }

    public function createBackup(Backup $backup): void
    {
        $this->repository->setServer($backup->server)->create($backup);
    }

    public function deleteBackup(Backup $backup): void
    {
        $client = $this->createClient($backup->backupHost);

        $client->deleteObject([
            'Bucket' => $backup->backupHost->configuration['bucket'],
            'Key' => "{$backup->server->uuid}/$backup->uuid.tar.gz",
        ]);
    }

    public function getDownloadLink(Backup $backup, User $user): string
    {
        $client = $this->createClient($backup->backupHost);

        $request = $client->createPresignedRequest(
            $client->getCommand('GetObject', [
                'Bucket' => $backup->backupHost->configuration['bucket'],
                'Key' => "{$backup->server->uuid}/$backup->uuid.tar.gz",
                'ContentType' => 'application/x-gzip',
            ]),
            CarbonImmutable::now()->addMinutes(5)
        );

        return $request->getUri()->__toString();
    }

    /** @return Component[] */
    public function getConfigurationForm(): array
    {
        return [
            TextInput::make('configuration.region')
                ->label(trans('admin/setting.backup.s3.default_region'))
                ->required(),
            TextInput::make('configuration.key')
                ->label(trans('admin/setting.backup.s3.access_key'))
                ->required(),
            TextInput::make('configuration.secret')
                ->label(trans('admin/setting.backup.s3.secret_key'))
                ->required(),
            TextInput::make('configuration.bucket')
                ->label(trans('admin/setting.backup.s3.bucket'))
                ->required(),
            TextInput::make('configuration.endpoint')
                ->label(trans('admin/setting.backup.s3.endpoint'))
                ->required(),
            Toggle::make('configuration.use_path_style_endpoint')
                ->label(trans('admin/setting.backup.s3.use_path_style_endpoint'))
                ->inline(false)
                ->onIcon(TablerIcon::Check)
                ->offIcon(TablerIcon::X)
                ->onColor('success')
                ->offColor('danger')
                ->live()
                ->stateCast(new BooleanStateCast(false)),
        ];
    }

    /** @return array{parts: string[], part_size: int} */
    public function getUploadParts(Backup $backup, int $size): array
    {
        $expires = CarbonImmutable::now()->addMinutes(config('backups.presigned_url_lifespan', 60));

        // Params for generating the presigned urls
        $params = [
            'Bucket' => $backup->backupHost->configuration['bucket'],
            'Key' => "{$backup->server->uuid}/$backup->uuid.tar.gz",
            'ContentType' => 'application/x-gzip',
        ];

        $storageClass = $backup->backupHost->configuration['storage_class'] ?? null;
        if (!is_null($storageClass)) {
            $params['StorageClass'] = $storageClass;
        }

        $client = $this->createClient($backup->backupHost);

        // Execute the CreateMultipartUpload request
        $result = $client->execute($client->getCommand('CreateMultipartUpload', $params));

        // Get the UploadId from the CreateMultipartUpload request, this is needed to create
        // the other presigned urls.
        $params['UploadId'] = $result->get('UploadId');

        // Retrieve configured part size
        $maxPartSize = config('backups.max_part_size', BackupRemoteUploadController::DEFAULT_MAX_PART_SIZE);
        if ($maxPartSize < BackupRemoteUploadController::MIN_MAX_PART_SIZE || $maxPartSize > BackupRemoteUploadController::MAX_MAX_PART_SIZE) {
            $maxPartSize = BackupRemoteUploadController::DEFAULT_MAX_PART_SIZE;
        }

        // Create as many UploadPart presigned urls as needed
        $parts = [];
        for ($i = 0; $i < ($size / $maxPartSize); $i++) {
            $parts[] = $client->createPresignedRequest(
                $client->getCommand('UploadPart', array_merge($params, ['PartNumber' => $i + 1])),
                $expires
            )->getUri()->__toString();
        }

        $maxConcurrentUploads = config('backups.max_concurrent_uploads', BackupRemoteUploadController::MAX_CONCURRENT_UPLOADS);

        // Set the upload_id on the backup in the database.
        $backup->update(['upload_id' => $params['UploadId']]);

        return [
            'parts' => $parts,
            'part_size' => $maxPartSize,
            'max_concurrent_uploads' => $maxConcurrentUploads,
        ];
    }

    /**
     * Marks a multipart upload in a given S3-compatible instance as failed or successful for the given backup.
     *
     * @param  ?array<array{int, etag: string, part_number: string}>  $parts
     *
     * @throws Exception
     */
    public function completeMultipartUpload(Backup $backup, bool $successful, ?array $parts): void
    {
        // This should never really happen, but if it does don't let us fall victim to Amazon's
        // wildly fun error messaging. Just stop the process right here.
        if (empty($backup->upload_id)) {
            // A failed backup doesn't need to error here, this can happen if the backup encounters
            // an error before we even start the upload. AWS gives you tooling to clear these failed
            // multipart uploads as needed too.
            if (!$successful) {
                return;
            }

            throw new Exception('Cannot complete backup request: no upload_id present on model.');
        }

        $params = [
            'Bucket' => $backup->backupHost->configuration['bucket'],
            'Key' => "{$backup->server->uuid}/$backup->uuid.tar.gz",
            'UploadId' => $backup->upload_id,
        ];

        $client = $this->createClient($backup->backupHost);

        if (!$successful) {
            $client->execute($client->getCommand('AbortMultipartUpload', $params));

            return;
        }

        // Otherwise send a CompleteMultipartUpload request.
        $params['MultipartUpload'] = [
            'Parts' => [],
        ];

        if (is_null($parts)) {
            $params['MultipartUpload']['Parts'] = $client->execute($client->getCommand('ListParts', $params))['Parts'];
        } else {
            foreach ($parts as $part) {
                $params['MultipartUpload']['Parts'][] = [
                    'ETag' => $part['etag'],
                    'PartNumber' => $part['part_number'],
                ];
            }
        }

        $client->execute($client->getCommand('CompleteMultipartUpload', $params));
    }
}
