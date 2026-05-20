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

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-5'),
    ],

    'trello' => [
        'api_key' => env('TRELLO_API_KEY'),
        'api_token' => env('TRELLO_API_TOKEN'),
        'template_board_id' => env('TRELLO_TEMPLATE_BOARD_ID'),
        'workspace_id' => env('TRELLO_WORKSPACE_ID'),
        'allow_billable_guest' => env('TRELLO_ALLOW_BILLABLE_GUEST', false),
        'board_name_suffix' => env('TRELLO_BOARD_NAME_SUFFIX', 'Writing Board'),
        'writing_requests_list_name' => env('TRELLO_WRITING_REQUESTS_LIST_NAME', 'Writing Requests'),
        'in_progress_list_name' => env('TRELLO_IN_PROGRESS_LIST_NAME', 'In Progress'),
        'completed_list_name' => env('TRELLO_COMPLETED_LIST_NAME', 'Completed'),
    ],

];
