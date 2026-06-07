<?php

declare(strict_types=1);

use Erp\Controller\AuthController;
use Erp\Controller\DashboardController;
use Erp\Controller\MaterialController;
use Erp\Controller\WarehouseController;
use Erp\Auth\AuthService;
use Erp\Auth\NativeSessionStore;
use Erp\Auth\PdoUserRepository;
use Erp\Core\App;
use Erp\Core\Autoloader;
use Erp\Core\Router;
use Erp\Database\Database;
use Erp\Http\NativeRedirector;
use Erp\Material\InMemoryMaterialRepository;
use Erp\Material\MaterialService;
use Erp\Material\PdoMaterialRepository;
use Erp\Warehouse\InMemoryWarehouseRepository;
use Erp\Warehouse\PdoWarehouseRepository;
use Erp\Warehouse\WarehouseService;

require_once dirname(__DIR__) . '/src/Core/Autoloader.php';

Autoloader::register(dirname(__DIR__) . '/src');

$appConfig = dirname(__DIR__) . '/config/app.php';
if (!is_file($appConfig)) {
    $appConfig = dirname(__DIR__) . '/config/app.example.php';
}
if (is_file($appConfig)) {
    $config = require $appConfig;
    App::setBasePath((string) ($config['base_path'] ?? '/erp'));
}

$router = new Router();
$session = new NativeSessionStore();
$redirector = new NativeRedirector();
$dashboard = new DashboardController($session, $redirector);
$authService = null;
$materialService = null;
$warehouseService = null;
$databaseConfig = dirname(__DIR__) . '/config/database.php';
if (is_file($databaseConfig)) {
    $pdo = Database::fromConfigFile($databaseConfig);
    $authService = new AuthService(new PdoUserRepository($pdo));
    $materialService = new MaterialService(new PdoMaterialRepository($pdo));
    $warehouseService = new WarehouseService(new PdoWarehouseRepository($pdo));
}
$auth = new AuthController($authService, $session, $redirector);
$materials = new MaterialController($materialService ?? new MaterialService(new InMemoryMaterialRepository()), $session, $redirector);
$warehouses = new WarehouseController($warehouseService ?? new WarehouseService(new InMemoryWarehouseRepository()), $session, $redirector);

$router->get('/', [$dashboard, 'index']);
$router->get('/login', [$auth, 'login']);
$router->post('/login', [$auth, 'submit']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/materials', [$materials, 'index']);
$router->post('/materials', [$materials, 'store']);
$router->get('/warehouses', [$warehouses, 'index']);
$router->post('/warehouses', [$warehouses, 'store']);
$router->get('/health', [$dashboard, 'health']);

return $router;
