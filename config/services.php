<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tusd' => [
        // Optional shared secret for tusd hook requests. When set, a hook
        // request must pass the internal IP check and also carry a matching
        // Hook-Secret header. tusd itself cannot add a fixed header (its
        // -hooks-http-forward-headers flag only copies headers from the
        // client's upload request), so leave this unset with the bundled
        // tusd. It is for setups where something between tusd and Erugo
        // adds the header.
        'hook_secret' => env('TUSD_HOOK_SECRET'),
    ],

];
