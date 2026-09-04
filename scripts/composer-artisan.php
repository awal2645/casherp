<?php

$artisan = dirname(__DIR__).DIRECTORY_SEPARATOR.'artisan';
$args = array_slice($argv, 1);

if ($args === []) {
    fwrite(STDERR, "Usage: composer-artisan.php <artisan arguments...>\n");
    exit(1);
}

$label = implode(' ', $args);

if (! is_file($artisan)) {
    fwrite(STDOUT, 'Skipping artisan '.$label.' (artisan not in this overlay).'.PHP_EOL);
    exit(0);
}

$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($artisan);
foreach ($args as $arg) {
    $command .= ' '.escapeshellarg($arg);
}

passthru($command, $code);
exit((int) $code);
