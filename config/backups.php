<?php

use App\Http\Controllers\Api\Remote\Backups\BackupRemoteUploadController;

return [
    // This value is used to determine the lifespan of UploadPart presigned urls that daemon
    // uses to upload backups to S3 storage.  Value is in minutes, so this would default to an hour.
    'presigned_url_lifespan' => (int) env('BACKUP_PRESIGNED_URL_LIFESPAN', 60),

    // This value defines the maximal size of a single part for the S3 multipart upload during backups
    // The maximal part size must be given in bytes. The default value is 128MiB.
    // Note that 5GiB is the maximum for a single part when using AWS S3.
    'max_part_size' => (int) env('BACKUP_MAX_PART_SIZE', BackupRemoteUploadController::DEFAULT_MAX_PART_SIZE),

    // The time to wait before automatically failing a backup, time is in minutes and defaults
    // to 6 hours.  To disable this feature, set the value to `0`.
    'prune_age' => (int) env('BACKUP_PRUNE_AGE', 360),

    // This value defines the maximum amount of concurrent requests PER BACKUP for S3 uploads
    // The default value is 10.
    // This value is not guaranteed to be reached (for instance, if there are fewer parts).
    // If multiple backups are being uploaded to S3 at the same time, each backup may have up to this limit of concurrent uploads.
    'max_concurrent_uploads' => (int) env('BACKUP_MAX_CONCURRENT_UPLOADS', BackupRemoteUploadController::MAX_CONCURRENT_UPLOADS),

    // Defines the backup creation throttle limits for users. In this default example, we allow
    // a user to create two (successful or pending) backups per 10 minutes. Even if they delete
    // a backup it will be included in the throttle count.
    //
    // Set the period to "0" to disable this throttle. The period is defined in seconds.
    'throttles' => [
        'limit' => (int) env('BACKUP_THROTTLE_LIMIT', 2),
        'period' => (int) env('BACKUP_THROTTLE_PERIOD', 600),
    ],
];
