<?php
// This is global bootstrap for autoloading
require_once __DIR__ . '/../vendor/autoload.php';

$kernel = \AspectMock\Kernel::getInstance();

$kernel->init([
    'debug' => true,
    'appDir' => __DIR__ . '/../',
    'cacheDir' => __DIR__ . '/tmp',
    'includePaths' => [__DIR__ . '/../src'],
    'excludePaths' => [__DIR__] // tests dir should be excluded
]);