<?php

return [
    /*
    | A cloud ERP must not be usable as a tunnel into private infrastructure.
    | Self-hosted installations that intentionally use an internal relay may
    | opt in explicitly in their environment configuration.
    */
    'allow_private_smtp_hosts' => (bool) env('MAIL_ALLOW_PRIVATE_SMTP_HOSTS', false),

    'default_timeout' => (int) env('MAIL_SMTP_TIMEOUT', 15),

    'provider_presets' => [
        'custom' => [
            'label' => 'Custom SMTP server',
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'help_url' => null,
        ],
        'google_workspace' => [
            'label' => 'Google Workspace / Gmail',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'help_url' => 'https://support.google.com/a/answer/176600',
        ],
        'microsoft_365' => [
            'label' => 'Microsoft 365 / Outlook',
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
            'help_url' => 'https://learn.microsoft.com/exchange/mail-flow-best-practices/how-to-set-up-a-multifunction-device-or-application-to-send-email-using-microsoft-365-or-office-365',
        ],
        'zoho' => [
            'label' => 'Zoho Mail',
            'host' => 'smtp.zoho.com',
            'port' => 587,
            'encryption' => 'tls',
            'help_url' => 'https://www.zoho.com/mail/help/zoho-smtp.html',
        ],
    ],
];
