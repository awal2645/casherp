<?php

if (! function_exists('isAppInstalled')) {
    function isAppInstalled(): bool
    {
        return file_exists(storage_path('installed')) || (bool) env('APP_INSTALLED', true);
    }
}

if (! function_exists('isPusherEnabled')) {
    function isPusherEnabled(): bool
    {
        $pusherSetting = config('broadcasting.connections.pusher.key');

        return ! empty($pusherSetting)
            && config('broadcasting.default') === 'pusher';
    }
}

if (! function_exists('humanFilesize')) {
    function humanFilesize($bytes, $decimals = 2)
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)).@$size[$factor];
    }
}
