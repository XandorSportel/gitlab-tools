<?php

return [

    'gitlab' => [
        'url' => env('GITLAB_URL', 'https://gitlab.com'),
        'project' => env('GITLAB_PROJECT'),
        'token' => env('GITLAB_TOKEN'),
        'completed_labels' => array_values(array_filter(array_map('trim', explode(',', env('GITLAB_COMPLETED_LABELS', 'In MR'))))),
        'workdays' => array_values(array_map('intval', array_intersect(
            ['1', '2', '3', '4', '5', '6', '7'],
            array_map('trim', explode(',', (string) env('GITLAB_WORKDAYS', '1,2,3,4,5,6,7'))),
        ))) ?: [1, 2, 3, 4, 5, 6, 7],
        'ignored_issue_labels' => array_values(array_filter(array_map('trim', explode(',', env('GITLAB_IGNORED_ISSUE_LABELS', 'Epic'))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
