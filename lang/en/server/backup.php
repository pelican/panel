<?php

return [
    'title' => 'Backups',
    'empty' => 'No Backups',
    'size' => 'Size',
    'created_at' => 'Created at',
    'status' => 'Status',
    'is_locked' => 'Lock Status',
    'backup_status' => [
        'in_progress' => 'In Progress',
        'successful' => 'Successful',
        'failed' => 'Failed',
    ],
    'notifications' => [
        'action' => 'Notification Settings',
        'toggle_manual' => 'Manual Backup Notifications',
        'helper_manual' => 'Receive a notification and email when a manually created backup for this server completes or fails.',
        'toggle_scheduled' => 'Scheduled Backup Notifications',
        'helper_scheduled' => 'Receive a notification and email when a scheduled backup for this server completes or fails.',
        'saved' => 'Notification Settings Updated',
    ],
    'actions' => [
        'create' => [
            'title' => 'Create Backup',
            'limit' => 'Backup Limit Reached',
            'created' => ':name created',
            'notification_success' => 'Backup Created Successfully',
            'notification_fail' => 'Backup Creation Failed',
            'name' => 'Name',
            'ignored' => 'Ignored Files & Directories',
            'locked' => 'Locked?',
            'lock_helper' => 'Prevents this backup from being deleted until explicitly unlocked.',
        ],
        'lock' => [
            'lock' => 'Lock',
            'unlock' => 'Unlock',
        ],
        'download' => 'Download',
        'rename' => [
            'title' => 'Rename',
            'new_name' => 'Backup Name',
            'notification_success' => 'Backup Renamed Successfully',
        ],
        'restore' => [
            'title' => 'Restore',
            'helper' => 'Your server will be stopped. You will not be able to control the power state, access the file manager, or create additional backups until this process is completed.',
            'delete_all' => 'Delete all files before restoring backup?',
            'notification_started' => 'Restoring Backup',
            'notification_success' => 'Backup Restored Successfully',
            'notification_fail' => 'Backup Restore Failed',
            'notification_fail_body_1' => 'This server is not currently in a state that allows for a backup to be restored.',
            'notification_fail_body_2' => 'This backup cannot be restored at this time: not completed or failed.',
        ],
        'delete' => [
            'title' => 'Delete Backup',
            'description' => 'Do you wish to delete :backup?',
            'notification_success' => 'Backup Deleted',
            'notification_fail' => 'Could not delete backup',
            'notification_fail_body' => 'Connection to node failed. Please try again.',
        ],
    ],
];
