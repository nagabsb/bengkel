<?php

define('LARAVEL_START', microtime(true));

$projectRoot = dirname(__DIR__);
$publicPath  = $projectRoot . '/public';

// Fix DOCUMENT_ROOT — vercel-php sets it to api/, override to public/
$_SERVER['DOCUMENT_ROOT']   = $publicPath;
$_SERVER['SCRIPT_FILENAME'] = $publicPath . '/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';

// Buat writable dirs di /tmp (satu-satunya yang bisa ditulis di Vercel)
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

putenv('APP_STORAGE_PATH=' . $tmpStorage);
$_ENV['APP_STORAGE_PATH']    = $tmpStorage;
$_SERVER['APP_STORAGE_PATH'] = $tmpStorage;

if (!getenv('LOG_CHANNEL'))      putenv('LOG_CHANNEL=stderr');
if (!getenv('QUEUE_CONNECTION')) putenv('QUEUE_CONNECTION=sync');

require $projectRoot . '/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require $projectRoot . '/bootstrap/app.php';

$app->handleRequest(\Illuminate\Http\Request::capture());
