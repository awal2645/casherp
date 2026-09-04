<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$applicationRoot = dirname(__DIR__).'/app.casherp.com';

if (! is_file($applicationRoot.'/vendor/autoload.php') || ! is_file($applicationRoot.'/bootstrap/app.php')) {
    http_response_code(503);
    exit('CashERP is temporarily unavailable.');
}

if (file_exists($maintenance = $applicationRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $applicationRoot.'/vendor/autoload.php';

$app = require_once $applicationRoot.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
