<?php

use Illuminate\Http\Request;

$public = getenv('TENANCY_BROWSER_DIRECTORY').'/public';
$path = realpath($public.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path && str_starts_with($path, realpath($public).DIRECTORY_SEPARATOR) && is_file($path)) {
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    header('Content-Type: '.match ($extension) {
        'js' => 'application/javascript',
        'css' => 'text/css',
        'woff2' => 'font/woff2',
        default => 'application/octet-stream',
    });
    readfile($path);

    return;
}

$app = require __DIR__.'/bootstrap.php';
$app->handleRequest(Request::capture());
