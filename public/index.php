<?php

declare(strict_types=1);

$router = require dirname(__DIR__) . '/bootstrap/web.php';

$basePath = '/erp';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath)) ?: '/';
}

echo $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);

