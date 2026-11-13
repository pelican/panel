<?php

return [
    'greeting' => 'Hello :name!',

    'account_created' => [
        'subject' => 'Account Created',
        'body' => 'You are receiving this email because an account has been created for you on :app.',
        'username' => 'Username: :username',
        'email' => 'Email: :email',
        'action' => 'Setup Your Account',
    ],

    'added_to_server' => [
        'subject' => 'Added to Server',
        'body' => 'You have been added as a subuser for the following server, allowing you certain control over the server.',
        'server_name' => 'Server Name: :name',
        'action' => 'Visit Server',
    ],

    'removed_from_server' => [
        'subject' => 'Removed from Server',
        'body' => 'You have been removed as a subuser for the following server.',
        'server_name' => 'Server Name: :name',
        'action' => 'Visit Panel',
    ],

    'server_installed' => [
        'subject' => 'Server Installed',
        'body' => 'Your server has finished installing and is now ready for you to use.',
        'server_name' => 'Server Name: :name',
        'action' => 'Login and Begin Using',
    ],

    'backup_completed' => [
        'subject_success' => 'Backup Completed',
        'subject_failed' => 'Backup Failed',
        'body_success' => 'The backup was created successfully.',
        'body_failed' => 'The backup creation failed.',
        'backup_name' => 'Backup Name: :name',
        'server_name' => 'Server Name: :name',
        'action' => 'View Backups',
    ],

    'mail_tested' => [
        'subject' => 'Panel Test Message',
        'body' => 'This is a test of the Panel mail system. You\'re good to go!',
    ],
];
