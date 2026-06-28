<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Hostinger split layout: public_html/ (web) + ../pixflow/ (Laravel root)
if (file_exists($maintenance = __DIR__.'/../pixflow/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../pixflow/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../pixflow/bootstrap/app.php';

$app->handleRequest(Request::capture());
