<?php
// This is global bootstrap for autoloading
require_once __DIR__ . '/../vendor/autoload.php';

// Make php-http/discovery resolve to the mock PSR-18 client during tests,
// so `new OpenId($config)` (which auto-discovers a client) works offline
// without pulling a real HTTP client into the dependencies.
\Http\Discovery\Psr18ClientDiscovery::prependStrategy(
    \Http\Discovery\Strategy\MockClientStrategy::class
);
