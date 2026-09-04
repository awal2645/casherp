<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$applicationRoot = dirname(__DIR__, 2);
$envPath = $applicationRoot.'/.env';

if (! is_file($envPath) || ! is_readable($envPath) || ! is_writable($envPath)) {
    fwrite(STDERR, "The application .env file is unavailable or not writable.\n");
    exit(1);
}

$content = file_get_contents($envPath);
if ($content === false) {
    fwrite(STDERR, "The application .env file could not be read.\n");
    exit(1);
}

$settings = [
    'APP_URL' => 'https://www.casherp.com',
    'ASSET_URL' => 'https://www.casherp.com',
    'CANONICAL_URL' => 'https://www.casherp.com',
    'CANONICAL_LEGACY_HOSTS' => 'casherp.com',
    'SESSION_DOMAIN' => 'www.casherp.com',
    'SESSION_SECURE_COOKIE' => 'true',
    'SESSION_SAME_SITE' => 'lax',
    'CORS_ALLOWED_ORIGINS' => 'https://www.casherp.com',
];

foreach ($settings as $key => $value) {
    $line = $key.'='.$value;
    $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';

    if (preg_match($pattern, $content) === 1) {
        $content = (string) preg_replace($pattern, $line, $content, 1);
    } else {
        $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
    }
}

$temporaryPath = $envPath.'.domain-migration.tmp';
if (file_put_contents($temporaryPath, $content, LOCK_EX) === false || ! rename($temporaryPath, $envPath)) {
    @unlink($temporaryPath);
    fwrite(STDERR, "The application .env file could not be updated atomically.\n");
    exit(1);
}

fwrite(STDOUT, "CashERP canonical-domain settings updated.\n");
