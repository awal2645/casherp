<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Provider secrets are read from the environment and must never be committed
    | to source control. The Google callback must exactly match the authorised
    | HTTPS redirect URI configured in Google Cloud.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'enabled' => filter_var(env('GOOGLE_SOCIAL_LOGIN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'redirect' => env(
            'GOOGLE_OAUTH_REDIRECT_URI',
            rtrim((string) env('APP_URL', 'https://www.casherp.com'), '/').'/auth/google/callback'
        ),
    ],

];
