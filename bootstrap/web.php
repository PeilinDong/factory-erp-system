<?php

declare(strict_types=1);

use Erp\Controller\AuthController;
use Erp\Controller\DashboardController;
use Erp\Auth\AuthService;
use Erp\Auth\PdoUserRepository;
use Erp\Core\Autoloader;
use Erp\Core\Router;
use Erp\Database\Database;

require_once dirname(__DIR__) . '/src/Core/Autoloader.php';

Autoloader::register(dirname(__DIR__) . '/src');

$router = new Router();
$dashboard = new DashboardController();
$authService = null;
$databaseConfig = dirname(__DIR__) . '/config/database.php';
if (is_file($databaseConfig)) {
    $authService = new AuthService(new PdoUserRepository(Database::fromConfigFile($databaseConfig)));
}
$auth = new AuthController($authService);

$router->get('/', [$dashboard, 'index']);
$router->get('/login', [$auth, 'login']);
$router->post('/login', [$auth, 'submit']);
$router->get('/health', [$dashboard, 'health']);

return $router;
