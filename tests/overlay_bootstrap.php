<?php

// Lets archive/overlay verification reuse a compatible dependency tree while
// keeping all application classes authoritative to this candidate directory.
$autoloadPath = getenv('CASHERP_TEST_VENDOR_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
if (! is_file($autoloadPath)) {
    fwrite(STDERR, "Set CASHERP_TEST_VENDOR_AUTOLOAD to a compatible vendor/autoload.php.\n");
    exit(2);
}

$loader = require $autoloadPath;
$root = dirname(__DIR__);
$loader->addPsr4('App\\', $root.'/app/', true);
$loader->addPsr4('Modules\\', $root.'/Modules/', true);
$loader->addPsr4('Tests\\', $root.'/tests/', true);
