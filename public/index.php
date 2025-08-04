<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__."/../config/bootstrap.php";

$kernel = new Kernel($_ENV['APP_ENV'] ?? 'prod', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN), __DIR__);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);