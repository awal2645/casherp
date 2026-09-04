<?php

return [
    'default_channels' => [
        ['name' => 'Company Announcements', 'slug' => 'announcements', 'type' => 'announcement', 'description' => 'Official company updates and notices.'],
        ['name' => 'General', 'slug' => 'general', 'type' => 'open', 'description' => 'Everyday internal discussion and collaboration.'],
        ['name' => 'Help & Questions', 'slug' => 'help-questions', 'type' => 'open', 'description' => 'Ask colleagues for guidance and share solutions.'],
    ],
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp'],
    'allowed_mimes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
        'image/png',
        'image/jpeg',
        'image/webp',
    ],
    'default_max_attachment_mb' => 10,
];
