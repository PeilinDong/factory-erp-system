<?php

declare(strict_types=1);

use Erp\Controller\AuthController;
use Erp\Controller\BomController;
use Erp\Controller\CustomerController;
use Erp\Controller\DashboardController;
use Erp\Controller\InventoryController;
use Erp\Controller\MaterialController;
use Erp\Controller\PlanningController;
use Erp\Controller\PurchaseController;
use Erp\Controller\SalesOrderController;
use Erp\Controller\SupplierController;
use Erp\Controller\UserController;
use Erp\Controller\WarehouseController;
use Erp\Controller\WorkOrderController;
use Erp\Bom\BomService;
use Erp\Bom\InMemoryBomRepository;
use Erp\Bom\PdoBomRepository;
use Erp\Auth\AuthService;
use Erp\Auth\InMemoryUserRepository;
use Erp\Auth\NativeSessionStore;
use Erp\Auth\PdoUserRepository;
use Erp\Auth\UserManagementService;
use Erp\Core\App;
use Erp\Core\Autoloader;
use Erp\Core\Router;
use Erp\Customer\CustomerService;
use Erp\Customer\InMemoryCustomerRepository;
use Erp\Customer\PdoCustomerRepository;
use Erp\Database\Database;
use Erp\Http\NativeRedirector;
use Erp\Inventory\InMemoryInventoryTransactionRepository;
use Erp\Inventory\InventoryService;
use Erp\Inventory\PdoInventoryTransactionRepository;
use Erp\Material\InMemoryMaterialRepository;
use Erp\Material\MaterialService;
use Erp\Material\PdoMaterialRepository;
use Erp\Planning\MaterialShortageService;
use Erp\Planning\PurchaseSuggestionService;
use Erp\Purchase\InMemoryPurchaseOrderRepository;
use Erp\Purchase\PdoPurchaseOrderRepository;
use Erp\Purchase\PurchaseOrderService;
use Erp\Sales\InMemorySalesOrderRepository;
use Erp\Sales\PdoSalesOrderRepository;
use Erp\Sales\SalesOrderService;
use Erp\Supplier\InMemorySupplierRepository;
use Erp\Supplier\PdoSupplierRepository;
use Erp\Supplier\SupplierService;
use Erp\Warehouse\InMemoryWarehouseRepository;
use Erp\Warehouse\PdoWarehouseRepository;
use Erp\Warehouse\WarehouseService;
use Erp\WorkOrder\InMemoryWorkOrderRepository;
use Erp\WorkOrder\PdoWorkOrderRepository;
use Erp\WorkOrder\WorkOrderService;

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
$userRepository = null;
$materialService = null;
$warehouseService = null;
$customerService = null;
$supplierService = null;
$inventoryService = null;
$bomService = null;
$purchaseService = null;
$salesOrderService = null;
$workOrderService = null;
$databaseConfig = dirname(__DIR__) . '/config/database.php';
if (is_file($databaseConfig)) {
    $pdo = Database::fromConfigFile($databaseConfig);
    $userRepository = new PdoUserRepository($pdo);
    $authService = new AuthService($userRepository);
    $materialRepository = new PdoMaterialRepository($pdo);
    $warehouseRepository = new PdoWarehouseRepository($pdo);
    $customerRepository = new PdoCustomerRepository($pdo);
    $supplierRepository = new PdoSupplierRepository($pdo);
    $bomRepository = new PdoBomRepository($pdo);
    $purchaseRepository = new PdoPurchaseOrderRepository($pdo);
    $salesOrderRepository = new PdoSalesOrderRepository($pdo);
    $workOrderRepository = new PdoWorkOrderRepository($pdo);
    $materialService = new MaterialService($materialRepository);
    $warehouseService = new WarehouseService($warehouseRepository);
    $customerService = new CustomerService($customerRepository);
    $supplierService = new SupplierService($supplierRepository);
    $bomService = new BomService($bomRepository, $materialRepository);
    $purchaseService = new PurchaseOrderService($purchaseRepository, $materialRepository, $supplierRepository);
    $salesOrderService = new SalesOrderService($salesOrderRepository, $customerRepository, $materialRepository);
    $workOrderService = new WorkOrderService($workOrderRepository, $bomService);
    $inventoryService = new InventoryService(
        new PdoInventoryTransactionRepository($pdo),
        $materialRepository,
        $warehouseRepository,
    );
}
$auth = new AuthController($authService, $session, $redirector);
$userRepository ??= new InMemoryUserRepository();
$materialRepository ??= new InMemoryMaterialRepository();
$warehouseRepository ??= new InMemoryWarehouseRepository();
$customerRepository ??= new InMemoryCustomerRepository();
$supplierRepository ??= new InMemorySupplierRepository();
$bomRepository ??= new InMemoryBomRepository();
$purchaseRepository ??= new InMemoryPurchaseOrderRepository();
$salesOrderRepository ??= new InMemorySalesOrderRepository();
$workOrderRepository ??= new InMemoryWorkOrderRepository();
$materialService ??= new MaterialService($materialRepository);
$warehouseService ??= new WarehouseService($warehouseRepository);
$customerService ??= new CustomerService($customerRepository);
$supplierService ??= new SupplierService($supplierRepository);
$bomService ??= new BomService($bomRepository, $materialRepository);
$purchaseService ??= new PurchaseOrderService($purchaseRepository, $materialRepository, $supplierRepository);
$salesOrderService ??= new SalesOrderService($salesOrderRepository, $customerRepository, $materialRepository);
$workOrderService ??= new WorkOrderService($workOrderRepository, $bomService);
$inventoryService ??= new InventoryService(
    new InMemoryInventoryTransactionRepository(),
    $materialRepository,
    $warehouseRepository,
);
$materials = new MaterialController($materialService, $session, $redirector);
$warehouses = new WarehouseController($warehouseService, $session, $redirector);
$customers = new CustomerController($customerService, $session, $redirector);
$suppliers = new SupplierController($supplierService, $session, $redirector);
$boms = new BomController($bomService, $materialService, $session, $redirector);
$salesOrders = new SalesOrderController($salesOrderService, $customerService, $materialService, $session, $redirector, $bomService, $workOrderService);
$purchases = new PurchaseController($purchaseService, $materialService, $warehouseService, $inventoryService, $session, $redirector, $supplierService);
$workOrders = new WorkOrderController($workOrderService, $bomService, $warehouseService, $inventoryService, $session, $redirector);
$inventory = new InventoryController($inventoryService, $materialService, $warehouseService, $session, $redirector);
$shortageService = new MaterialShortageService($workOrderService, $inventoryService);
$planning = new PlanningController(
    $shortageService,
    new PurchaseSuggestionService($shortageService),
    $purchaseService,
    $session,
    $redirector,
);
$users = new UserController(new UserManagementService($userRepository), $session, $redirector);
$dashboard = new DashboardController($session, $redirector, $inventoryService);

$router->get('/', [$dashboard, 'index']);
$router->get('/login', [$auth, 'login']);
$router->post('/login', [$auth, 'submit']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/users', [$users, 'index']);
$router->post('/users', [$users, 'store']);
$router->post('/users/status', [$users, 'status']);
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
$router->get('/customers', [$customers, 'index']);
$router->post('/customers', [$customers, 'store']);
$router->post('/customers/status', [$customers, 'status']);
$router->get('/suppliers', [$suppliers, 'index']);
$router->post('/suppliers', [$suppliers, 'store']);
$router->post('/suppliers/status', [$suppliers, 'status']);
$router->get('/boms', [$boms, 'index']);
$router->post('/boms', [$boms, 'store']);
$router->post('/boms/status', [$boms, 'status']);
$router->get('/sales-orders', [$salesOrders, 'index']);
$router->post('/sales-orders', [$salesOrders, 'store']);
$router->post('/sales-orders/plan', [$salesOrders, 'plan']);
$router->get('/purchases', [$purchases, 'index']);
$router->post('/purchases', [$purchases, 'store']);
$router->post('/purchases/status', [$purchases, 'status']);
$router->post('/purchases/receive', [$purchases, 'receive']);
$router->post('/purchases/return', [$purchases, 'return']);
$router->get('/work-orders', [$workOrders, 'index']);
$router->post('/work-orders', [$workOrders, 'store']);
$router->post('/work-orders/issue', [$workOrders, 'issue']);
$router->post('/work-orders/complete', [$workOrders, 'complete']);
$router->get('/planning/shortages', [$planning, 'shortages']);
$router->get('/planning/purchase-suggestions', [$planning, 'purchaseSuggestions']);
$router->post('/planning/purchase-suggestions/convert', [$planning, 'convertPurchaseSuggestion']);
$router->get('/inventory', [$inventory, 'index']);
$router->post('/inventory', [$inventory, 'store']);
$router->get('/inventory/balances', [$inventory, 'balances']);
$router->get('/inventory/trace', [$inventory, 'trace']);
$router->get('/health', [$dashboard, 'health']);

return $router;
