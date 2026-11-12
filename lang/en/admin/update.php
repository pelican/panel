<?php

return [
    'button' => 'Update now',
    'modal' => [
        'heading' => 'Update to :version?',
        'description' => 'The panel downloads and prepares :to first, then goes into maintenance mode for a minute or two while it switches over from :from and runs the migrations. Your code and database are backed up and restored automatically if anything fails.',
        'warnings' => 'Warnings',
        'plugins' => 'Update anyway. These enabled plugins don\'t support :version and will be marked incompatible: :plugins',
        'no_backup' => 'I have my own database backup. No database dump tool (mysqldump, mariadb-dump or pg_dump) was found, so the updater can\'t back up or restore the database.',
    ],
    'progress' => [
        'heading' => 'Panel update',
        'dismiss' => 'Dismiss',
        'log' => 'Log',
        'states' => [
            'queued' => 'Starting the update to :to…',
            'running' => 'Updating from :from to :to…',
            'succeeded' => 'Updated from :from to :to.',
            'failed' => 'The update to :to failed. Everything was rolled back to :from.',
            'rollback_failed' => 'The update to :to failed and could not be rolled back. The panel is still in maintenance mode, check the log and the backups.',
        ],
        'steps' => [
            'preflight' => 'Checking requirements',
            'download' => 'Downloading and verifying the release',
            'dependencies' => 'Installing dependencies',
            'maintenance' => 'Entering maintenance mode',
            'backup' => 'Backing up the database',
            'swap' => 'Switching to the new files',
            'migrate' => 'Running migrations',
            'finish' => 'Finishing up',
        ],
    ],
    'preflight' => [
        'docker' => 'Docker installs update by pulling the new image and recreating the container.',
        'canary' => 'Canary builds can\'t be updated automatically.',
        'no_target' => 'The latest version could not be determined.',
        'not_newer' => ':target is not newer than the installed :current.',
        'running' => 'An update is already running.',
        'proc_open' => 'The proc_open function is disabled, so the updater can\'t run commands.',
        'binary' => 'The :binary command could not be found.',
        'not_writable' => ':path is not writable by the web server user.',
        'disk_space' => 'At least :required of free disk space is needed.',
        'no_yarn' => 'Some plugins ship frontend assets, but yarn is not installed. Their styles and scripts won\'t be rebuilt.',
        'plugins' => 'These enabled plugins will be marked incompatible: :plugins',
        'no_dump_tool' => 'No database dump tool was found, so the database can\'t be backed up automatically.',
    ],
    'notifications' => [
        'started' => 'Update started',
        'started_body' => 'You can follow its progress on the dashboard. You stay logged in while the panel is in maintenance mode.',
        'launch_failed' => 'Could not start the update process.',
        'failed' => 'Could not start the update',
    ],
];
