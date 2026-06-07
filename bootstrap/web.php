<?php

declare(strict_types=1);

use Erp\Controller\AuthController;
use Erp\Controller\BomController;
use Erp\Controller\DashboardController;
use Erp\Controller\InventoryController;
use Erp\Controller\MaterialController;
use Erp\Controller\WarehouseController;
use Erp\Bom\BomService;
use Erp\Bom\InMemoryBomRepository;
use Erp\Bom\PdoBomRepository;
use Erp\Auth\AuthService;
use Erp\Auth\NativeSessionStore;
use Erp\Auth\PdoUserRepository;
use Erp\Core\App;
use Erp\Core\Autoloader;
use Erp\Core\Router;
use Erp\Database\Database;
use Erp\Http\NativeRedirector;
use Erp\Inventory\InMemoryInventoryTransactionRepository;
use Erp\Inventory\InventoryService;
use Erp\Inventory\PdoInventoryTransactionRepository;
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
$authService = null;
$materialService = null;
$warehouseService = null;
$inventoryService = null;
$bomService = null;
$databaseConfig = dirname(__DIR__) . '/config/database.php';
if (is_file($databaseConfig)) {
    $pdo = Database::fromConfigFile($databaseConfig);
    $authService = new AuthService(new PdoUserRepository($pdo));
    $materialRepository = new PdoMaterialRepository($pdo);
    $warehouseRepository = new PdoWarehouseRepository($pdo);
    $bomRepository = new PdoBomRepository($pdo);
    $materialService = new MaterialService($materialRepository);
    $warehouseService = new WarehouseService($warehouseRepository);
    $bomService = new BomService($bomRepository, $materialRepository);
    $inventoryService = new InventoryService(
        new PdoInventoryTransactionRepository($pdo),
        $materialRepository,
        $warehouseRepository,
    );
}
$auth = new AuthController($authService, $session, $redirector);
$materialRepository ??= new InMemoryMaterialRepository();
$warehouseRepository ??= new InMemoryWarehouseRepository();
$bomRepository ??= new InMemoryBomRepository();
$materialService ??= new MaterialService($materialRepository);
$warehouseService ??= new WarehouseService($warehouseRepository);
$bomService ??= new BomService($bomRepository, $materialRepository);
$inventoryService ??= new InventoryService(
    new InMemoryInventoryTransactionRepository(),
    $materialRepository,
    $warehouseRepository,
);
$materials = new MaterialController($materialService, $session, $redirector);
$warehouses = new WarehouseController($warehouseService, $session, $redirector);
$boms = new BomController($bomService, $materialService, $session, $redirector);
$inventory = new InventoryController($inventoryService, $materialService, $warehouseService, $session, $redirector);
$dashboard = new DashboardController($session, $redirector, $inventoryService);

$router->get('/', [$dashboard, 'index']);
$router->get('/login', [$auth, 'login']);
$router->post('/login', [$auth, 'submit']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/materials', [$materials, 'index']);
$router->post('/materials', [$materials, 'store']);
$router->get('/materials/edit', [$materials, 'edit']);
$router->post('/materials/update', [$materials, 'update']);
$router->post('/materials/status', [$materials, 'status']);
$router->get('/warehouses', [$warehouses, 'index']);
$router->post('/warehouses', [$warehouses, 'store']);
$router->get('/warehouses/edit', [$warehouses, 'edit']);
$router->post('/warehouses/update', [$warehouses, 'update']);
$router->post('/warehouses/status', [$warehouses, 'status']);
$router->get('/boms', [$boms, 'index']);
$router->post('/boms', [$boms, 'store']);
$router->get('/inventory', [$inventory, 'index']);
$router->post('/inventory', [$inventory, 'store']);
$router->get('/inventory/balances', [$inventory, 'balances']);
$router->get('/health', [$dashboard, 'health']);

return $router;
