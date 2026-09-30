<?php
// Copy this file to app/config.php and fill in your values.
return [
    'app_url'   => 'https://yourdomain.com',          // no trailing slash; folder included if in a subfolder
    'timezone'  => 'UTC',
    'cron_key'  => 'change-this-to-a-long-random-string',

    'db' => [
        'host' => 'localhost',
        'name' => 'cpanelprefix_gmbcrm',
        'user' => 'cpanelprefix_gmbuser',
        'pass' => 'database-password',
    ],

    'google' => [
        'client_id'     => 'xxxx.apps.googleusercontent.com',
        'client_secret' => 'xxxx',
        // must match an "Authorized redirect URI" in Google Cloud Console
        'redirect_uri'  => 'https://yourdomain.com/google_callback.php',
    ],
];
