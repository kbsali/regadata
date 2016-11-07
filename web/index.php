<?php

$app = require __DIR__ . '/../src/app.php';

ini_set('display_errors', $app['debug']);
if ($app['debug']) {
    error_reporting(-1);
}

$app['http_cache']->run();
