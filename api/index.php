<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('LARAVEL_START', microtime(true));

$projectRoot = dirname(__DIR__);
$publicPath  = $projectRoot . '/public';

$_SERVER['DOCUMENT_ROOT']   = $publicPath;
$_SERVER['SCRIPT_FILENAME'] = $publicPath . '/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';

$tmpStorage = '/tmp/storage';
foreach ([
    $tmpStorage . '/framework/cache/data',
    $tmpStorage . '/framework/sessions',
    $tmpStorage . '/framework/views',
    $tmpStorage . '/app/public',
    $tmpStorage . '/logs',
] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$tmpCache = '/tmp/bootstrap/cache';
if (!is_dir($tmpCache)) {
    mkdir($tmpCache, 0755, true);
}

putenv('APP_STORAGE_PATH=' . $tmpStorage);
$_ENV['APP_STORAGE_PATH']    = $tmpStorage;
$_SERVER['APP_STORAGE_PATH'] = $tmpStorage;

if (!getenv('LOG_CHANNEL'))      putenv('LOG_CHANNEL=stderr');
if (!getenv('QUEUE_CONNECTION')) putenv('QUEUE_CONNECTION=sync');

set_exception_handler(function (\Throwable $e) {
    http_response_code(200);
    header('Content-Type: text/plain');
    echo "EXCEPTION: " . get_class($e) . "\n";
    echo "MESSAGE: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo $e->getTraceAsString();
    $prev = $e->getPrevious();
    while ($prev) {
        echo "\n\nCAUSED BY: " . get_class($prev) . "\n";
        echo "MESSAGE: " . $prev->getMessage() . "\n";
        echo "FILE: " . $prev->getFile() . ":" . $prev->getLine() . "\n";
        $prev = $prev->getPrevious();
    }
    exit(1);
});

require $projectRoot . '/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require $projectRoot . '/bootstrap/app.php';

$app->useBootstrapPath('/tmp/bootstrap');

$app->handleRequest(\Illuminate\Http\Request::capture());
