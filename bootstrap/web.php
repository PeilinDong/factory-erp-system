<?php

declare(strict_types=1);

use Erp\Controller\AuthController;
use Erp\Controller\DashboardController;
use Erp\Core\Autoloader;
use Erp\Core\Router;

require_once dirname(__DIR__) . '/src/Core/Autoloader.php';

Autoloader::register(dirname(__DIR__) . '/src');

$router = new Router();
$dashboard = new DashboardController();
$auth = new AuthController();

$router->get('/', [$dashboard, 'index']);
$router->get('/login', [$auth, 'login']);
$router->get('/health', [$dashboard, 'health']);

return $router;

