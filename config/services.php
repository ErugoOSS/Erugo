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
        // Shared key that every tusd hook request must carry. Hook requests
        // without it are refused, and if it is empty all of them are, so
        // uploads stop working. In the Docker image, start-container makes a
        // random key at every boot unless TUSD_HOOK_SECRET is set, and hands
        // it to tusd as the password in the hook URL
        // (http://tusd:<key>@127.0.0.1/api/tusd-hooks), which arrives here as
        // HTTP basic auth. A proxy in front of Erugo can send it in a
        // Hook-Secret header instead. Caddy also refuses /api/tusd-hooks to
        // anything that isn't connecting from loopback.
        'hook_secret' => env('TUSD_HOOK_SECRET'),
    ],

];
