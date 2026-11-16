<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Laravel project location
|--------------------------------------------------------------------------
| Default: this file lives in <project>/public.
| cPanel: if you copy the contents of /public into public_html and keep the
| project in e.g. /home/USER/interior, change this to:
|     $basePath = __DIR__.'/../interior';
*/
$basePath = __DIR__.'/..';

if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $basePath.'/vendor/autoload.php';

$app = require_once $basePath.'/bootstrap/app.php';

// Uploads & frontend build always resolve to the folder this file is in.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
