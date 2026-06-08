<?php

declare(strict_types=1);

namespace Tests\Unit;

use Erp\Cli\Application;
use Erp\Auth\AuthService;
use Erp\Auth\InMemoryUserRepository;
use Erp\Auth\InMemorySessionStore;
use Erp\Core\App;
use Erp\Core\Config;
use Erp\Core\Router;
use Erp\Http\InMemoryRedirector;
use Erp\Controller\AuthController;
use Erp\Controller\DashboardController;
use Erp\Controller\MaterialController;
use Erp\Material\InMemoryMaterialRepository;
use Erp\Material\MaterialService;
use Erp\Warehouse\InMemoryWarehouseRepository;
use Erp\Warehouse\WarehouseService;
use Erp\Controller\WarehouseController;
use Erp\Controller\InventoryController;
use Erp\Inventory\InMemoryInventoryTransactionRepository;
use Erp\Inventory\InventoryService;
use Erp\Bom\BomService;
use Erp\Bom\InMemoryBomRepository;
use Erp\Controller\BomController;
use Erp\Purchase\InMemoryPurchaseOrderRepository;
use Erp\Purchase\PurchaseOrderService;
use Erp\Controller\PurchaseController;
use Erp\WorkOrder\InMemoryWorkOrderRepository;
use Erp\WorkOrder\WorkOrderService;
use Erp\Controller\WorkOrderController;
use Tests\TestCase;

final class FoundationTest extends TestCase
{
    public function testConfigReadsNestedValuesWithDefaults(): void
    {
        $config = new Config([
            'app' => [
                'name' => 'Factory ERP',
                'base_path' => '/erp',
            ],
        ]);

        $this->assertSame('Factory ERP', $config->get('app.name'));
        $this->assertSame('/erp', $config->get('app.base_path'));
        $this->assertSame('fallback', $config->get('database.host', 'fallback'));
    }

    public function testRouterDispatchesMatchingGetRoute(): void
    {
        $router = new Router();
        $router->get('/health', static fn (): string => 'ok');
        $router->post('/submit', static fn (): string => 'posted');

        $this->assertSame('ok', $router->dispatch('GET', '/health'));
        $this->assertSame('posted', $router->dispatch('POST', '/submit'));
        $this->assertStringContains('404', $router->dispatch('GET', '/missing'));
    }

    public function testRouterDoesNotEmitHeaderWarningAfterOutputStarted(): void
    {
        $router = new Router();

        $previous = set_error_handler(static function (int $severity, string $message): never {
            throw new \RuntimeException($message, $severity);
        });

        echo '';

        try {
            $this->assertStringContains('404', $router->dispatch('GET', '/missing'));
        } finally {
            restore_error_handler();
            if ($previous !== null) {
                set_error_handler($previous);
            }
        }
    }

    public function testCliHealthCommandReportsApplicationStatus(): void
    {
        $app = Application::default();

        $output = $app->run(['erpctl', 'health']);

        $this->assertStringContains('Factory ERP health check', $output);
        $this->assertStringContains('PHP', $output);
        $this->assertStringContains('OK', $output);
    }

    public function testMigrationDryRunListsFoundationTables(): void
    {
        $app = Application::default();

        $output = $app->run(['erpctl', 'migrate', '--dry-run']);

        $this->assertStringContains('users', $output);
        $this->assertStringContains('roles', $output);
        $this->assertStringContains('materials', $output);
        $this->assertStringContains('inventory_transactions', $output);
        $this->assertStringContains('boms', $output);
        $this->assertStringContains('bom_items', $output);
        $this->assertStringContains('purchase_orders', $output);
        $this->assertStringContains('purchase_order_items', $output);
        $this->assertStringContains('work_orders', $output);
    }

    public function testLoginPageContainsChineseProductPositioning(): void
    {
        $router = require dirname(__DIR__, 2) . '/bootstrap/web.php';

        $html = $router->dispatch('GET', '/login');

        $this->assertStringContains('中国中小制造企业', $html);
        $this->assertStringContains('登录', $html);
    }

    public function testAppBuildsUrlsFromConfiguredBasePath(): void
    {
        App::setBasePath('/custom');

        $this->assertSame('/custom/login', App::url('/login'));
        $this->assertSame('/custom/assets/app.css', App::asset('/assets/app.css'));

        App::setBasePath('/erp');
    }

    public function testLoginFormContainsCsrfTokenAndConfiguredAction(): void
    {
        App::setBasePath('/custom');
        $session = new InMemorySessionStore();
        $controller = new AuthController(null, $session, new InMemoryRedirector());

        $html = $controller->login();

        $this->assertStringContains('name="csrf_token"', $html);
        $this->assertStringContains('action="/custom/login"', $html);
        $this->assertStringContains($session->csrfToken(), $html);

        App::setBasePath('/erp');
    }

    public function testDashboardRedirectsGuestToLogin(): void
    {
        App::setBasePath('/erp');
        $redirector = new InMemoryRedirector();
        $controller = new DashboardController(new InMemorySessionStore(), $redirector);

        $html = $controller->index();

        $this->assertSame('', $html);
        $this->assertSame('/erp/login', $redirector->lastLocation());
    }

    public function testDashboardExposesImplementedModulesWithoutDeadLinks(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => 'Admin']);
        $controller = new DashboardController($session, new InMemoryRedirector());

        $html = $controller->index();

        $this->assertStringContains('href="/erp/materials"', $html);
        $this->assertStringContains('href="/erp/warehouses"', $html);
        $this->assertStringContains('href="/erp/inventory"', $html);
        $this->assertStringContains('仓库档案', $html);
        $this->assertStringNotContains('href="#"', $html);
        $this->assertStringNotContains('Dashboard', $html);
    }

    public function testDashboardShowsInventoryMetricsWhenInventoryServiceIsAvailable(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => 'Admin']);
        $materials = new InMemoryMaterialRepository();
        $warehouses = new InMemoryWarehouseRepository();
        $material = $materials->create([
            'code' => 'MAT-001',
            'name' => 'Steel Screw',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $warehouse = $warehouses->create([
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
        ]);
        $inventory = new InventoryService(
            new InMemoryInventoryTransactionRepository(),
            $materials,
            $warehouses,
        );
        $inventory->record([
            'material_id' => (string) $material['id'],
            'warehouse_id' => (string) $warehouse['id'],
            'transaction_type' => 'outbound',
            'quantity' => '2',
        ]);
        $controller = new DashboardController($session, new InMemoryRedirector(), $inventory);

        $html = $controller->index();

        $this->assertStringContains('库存余额', $html);
        $this->assertStringContains('负库存预警', $html);
        $this->assertStringContains('1 项', $html);
        $this->assertStringContains('库存流水', $html);
        $this->assertStringContains('1 条', $html);
    }

    public function testLoginRejectsMissingCsrfToken(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $redirector = new InMemoryRedirector();
        $auth = new AuthService(new InMemoryUserRepository([
            [
                'id' => 1,
                'email' => 'admin@goenn.online',
                'name' => '管理员',
                'password_hash' => password_hash('CorrectPassword123', PASSWORD_DEFAULT),
                'is_active' => 1,
            ],
        ]));
        $controller = new AuthController($auth, $session, $redirector);

        $controller->submit([
            'email' => 'admin@goenn.online',
            'password' => 'CorrectPassword123',
        ]);

        $this->assertSame('/erp/login?error=csrf', $redirector->lastLocation());
        $this->assertSame(null, $session->user());
    }

    public function testLoginStoresUserAndRegeneratesSessionOnSuccess(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $token = $session->csrfToken();
        $redirector = new InMemoryRedirector();
        $auth = new AuthService(new InMemoryUserRepository([
            [
                'id' => 1,
                'email' => 'admin@goenn.online',
                'name' => '管理员',
                'password_hash' => password_hash('CorrectPassword123', PASSWORD_DEFAULT),
                'is_active' => 1,
            ],
        ]));
        $controller = new AuthController($auth, $session, $redirector);

        $controller->submit([
            'email' => 'admin@goenn.online',
            'password' => 'CorrectPassword123',
            'csrf_token' => $token,
        ]);

        $this->assertSame('/erp/', $redirector->lastLocation());
        $this->assertSame('admin@goenn.online', $session->user()['email']);
        $this->assertSame(1, $session->regenerateCount());
    }

    public function testLogoutClearsSessionAndRedirectsToLogin(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $token = $session->csrfToken();
        $redirector = new InMemoryRedirector();
        $controller = new AuthController(null, $session, $redirector);

        $controller->logout(['csrf_token' => $token]);

        $this->assertSame(null, $session->user());
        $this->assertSame('/erp/login', $redirector->lastLocation());
    }

    public function testAuthServiceAuthenticatesStoredPasswordHash(): void
    {
        $repository = new InMemoryUserRepository([
            [
                'id' => 7,
                'email' => 'admin@goenn.online',
                'name' => '管理员',
                'password_hash' => password_hash('CorrectPassword123', PASSWORD_DEFAULT),
                'is_active' => 1,
            ],
        ]);
        $auth = new AuthService($repository);

        $user = $auth->attempt('admin@goenn.online', 'CorrectPassword123');

        $this->assertSame(7, $user['id']);
        $this->assertSame('admin@goenn.online', $user['email']);
    }

    public function testAuthServiceRejectsInvalidOrInactiveUsers(): void
    {
        $repository = new InMemoryUserRepository([
            [
                'id' => 8,
                'email' => 'disabled@example.com',
                'name' => '停用用户',
                'password_hash' => password_hash('CorrectPassword123', PASSWORD_DEFAULT),
                'is_active' => 0,
            ],
        ]);
        $auth = new AuthService($repository);

        $this->assertSame(null, $auth->attempt('missing@example.com', 'CorrectPassword123'));
        $this->assertSame(null, $auth->attempt('disabled@example.com', 'CorrectPassword123'));
        $this->assertSame(null, $auth->attempt('disabled@example.com', 'WrongPassword123'));
    }

    public function testCreateAdminCommandCanPrepareDatabaseInsert(): void
    {
        $app = Application::default();

        $output = $app->run([
            'erpctl',
            'create-admin',
            '--email=admin@goenn.online',
            '--password=CorrectPassword123',
            '--dry-run',
        ]);

        $this->assertStringContains('Admin account validated for admin@goenn.online', $output);
        $this->assertStringContains('Password hash algorithm:', $output);
    }

    public function testMaterialServiceCreatesAndListsMaterials(): void
    {
        $repository = new InMemoryMaterialRepository();
        $service = new MaterialService($repository);

        $material = $service->create([
            'code' => 'MAT-001',
            'name' => '不锈钢螺丝',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);

        $this->assertSame('MAT-001', $material['code']);
        $this->assertSame('不锈钢螺丝', $material['name']);
        $this->assertSame(1, count($service->list()));
    }

    public function testMaterialServiceRejectsInvalidMaterialCode(): void
    {
        $service = new MaterialService(new InMemoryMaterialRepository());

        try {
            $service->create([
                'code' => '中文编码',
                'name' => '测试物料',
                'base_unit' => 'pcs',
                'material_type' => 'purchased',
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContains('物料编码', $exception->getMessage());
            return;
        }

        throw new \RuntimeException('Expected invalid material code to be rejected');
    }

    public function testMaterialServiceSearchesMaterialsByCodeNameAndSpecification(): void
    {
        $service = new MaterialService(new InMemoryMaterialRepository());
        $service->create([
            'code' => 'MAT-001',
            'name' => 'Steel Screw',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $service->create([
            'code' => 'MAT-002',
            'name' => 'Copper Plate',
            'specification' => 'T2',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);

        $this->assertSame(1, count($service->search('screw')));
        $this->assertSame(1, count($service->search('T2')));
        $this->assertSame(2, count($service->search('')));
    }

    public function testMaterialServiceUpdatesAndTogglesMaterialStatus(): void
    {
        $service = new MaterialService(new InMemoryMaterialRepository());
        $material = $service->create([
            'code' => 'mat-001',
            'name' => '旧物料',
            'specification' => 'M6',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);

        $updated = $service->update($material['id'], [
            'code' => 'mat-002',
            'name' => '新物料',
            'specification' => 'M8',
            'base_unit' => 'kg',
            'material_type' => 'manufactured',
        ]);
        $inactive = $service->setActive($material['id'], false);
        $active = $service->setActive($material['id'], true);

        $this->assertSame('MAT-002', $updated['code']);
        $this->assertSame('新物料', $updated['name']);
        $this->assertSame('M8', $updated['specification']);
        $this->assertSame('kg', $updated['base_unit']);
        $this->assertSame('manufactured', $updated['material_type']);
        $this->assertSame(0, $inactive['is_active']);
        $this->assertSame(1, $active['is_active']);
    }

    public function testMaterialPageRedirectsGuestToLogin(): void
    {
        App::setBasePath('/erp');
        $redirector = new InMemoryRedirector();
        $controller = new MaterialController(
            new MaterialService(new InMemoryMaterialRepository()),
            new InMemorySessionStore(),
            $redirector,
        );

        $html = $controller->index();

        $this->assertSame('', $html);
        $this->assertSame('/erp/login', $redirector->lastLocation());
    }

    public function testMaterialPageShowsListAndCreateFormForLoggedInUser(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $repository = new InMemoryMaterialRepository();
        $service = new MaterialService($repository);
        $service->create([
            'code' => 'MAT-001',
            'name' => '不锈钢螺丝',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $controller = new MaterialController($service, $session, new InMemoryRedirector());

        $html = $controller->index();

        $this->assertStringContains('物料档案', $html);
        $this->assertStringContains('MAT-001', $html);
        $this->assertStringContains('name="csrf_token"', $html);
        $this->assertStringContains('action="/erp/materials"', $html);
    }

    public function testMaterialPageProvidesEditAndStatusActions(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $service = new MaterialService(new InMemoryMaterialRepository());
        $service->create([
            'code' => 'MAT-001',
            'name' => '钢螺丝',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $controller = new MaterialController($service, $session, new InMemoryRedirector());

        $html = $controller->index();

        $this->assertStringContains('编辑', $html);
        $this->assertStringContains('停用', $html);
        $this->assertStringContains('href="/erp/materials/edit?id=1"', $html);
        $this->assertStringContains('action="/erp/materials/status"', $html);
    }

    public function testMaterialControllerUpdatesMaterialAndRedirects(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $service = new MaterialService(new InMemoryMaterialRepository());
        $material = $service->create([
            'code' => 'MAT-001',
            'name' => '旧物料',
            'specification' => 'M6',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $redirector = new InMemoryRedirector();
        $controller = new MaterialController($service, $session, $redirector);

        $controller->update([
            'id' => (string) $material['id'],
            'csrf_token' => $session->csrfToken(),
            'code' => 'MAT-002',
            'name' => '新物料',
            'specification' => 'M8',
            'base_unit' => 'kg',
            'material_type' => 'manufactured',
        ]);

        $this->assertSame('/erp/materials?updated=1', $redirector->lastLocation());
        $this->assertSame('新物料', $service->list()[0]['name']);
    }

    public function testMaterialControllerTogglesMaterialStatusAndRedirects(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $service = new MaterialService(new InMemoryMaterialRepository());
        $material = $service->create([
            'code' => 'MAT-001',
            'name' => '钢螺丝',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $redirector = new InMemoryRedirector();
        $controller = new MaterialController($service, $session, $redirector);

        $controller->status([
            'id' => (string) $material['id'],
            'is_active' => '0',
            'csrf_token' => $session->csrfToken(),
        ]);

        $this->assertSame('/erp/materials?status=1', $redirector->lastLocation());
        $this->assertSame(0, $service->list()[0]['is_active']);
    }

    public function testWarehouseServiceCreatesAndListsWarehouses(): void
    {
        $repository = new InMemoryWarehouseRepository();
        $service = new WarehouseService($repository);

        $warehouse = $service->create([
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
        ]);

        $this->assertSame('WH-001', $warehouse['code']);
        $this->assertSame('Main Warehouse', $warehouse['name']);
        $this->assertSame(1, $warehouse['is_active']);
        $this->assertSame(1, count($service->list()));
    }

    public function testWarehouseServiceRejectsInvalidWarehouseCode(): void
    {
        $service = new WarehouseService(new InMemoryWarehouseRepository());

        try {
            $service->create([
                'code' => 'x',
                'name' => 'Main Warehouse',
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContains('warehouse code', $exception->getMessage());
            return;
        }

        throw new \RuntimeException('Expected invalid warehouse code to be rejected');
    }

    public function testWarehouseServiceSearchesWarehousesByCodeAndName(): void
    {
        $service = new WarehouseService(new InMemoryWarehouseRepository());
        $service->create([
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
        ]);
        $service->create([
            'code' => 'WH-002',
            'name' => 'Finished Goods',
        ]);

        $this->assertSame(1, count($service->search('main')));
        $this->assertSame(1, count($service->search('WH-002')));
        $this->assertSame(2, count($service->search('')));
    }

    public function testWarehouseServiceUpdatesAndTogglesWarehouseStatus(): void
    {
        $service = new WarehouseService(new InMemoryWarehouseRepository());
        $warehouse = $service->create([
            'code' => 'wh-001',
            'name' => '旧仓库',
        ]);

        $updated = $service->update($warehouse['id'], [
            'code' => 'wh-002',
            'name' => '新仓库',
        ]);
        $inactive = $service->setActive($warehouse['id'], false);
        $active = $service->setActive($warehouse['id'], true);

        $this->assertSame('WH-002', $updated['code']);
        $this->assertSame('新仓库', $updated['name']);
        $this->assertSame(0, $inactive['is_active']);
        $this->assertSame(1, $active['is_active']);
    }

    public function testWarehousePageRedirectsGuestToLogin(): void
    {
        App::setBasePath('/erp');
        $redirector = new InMemoryRedirector();
        $controller = new WarehouseController(
            new WarehouseService(new InMemoryWarehouseRepository()),
            new InMemorySessionStore(),
            $redirector,
        );

        $html = $controller->index();

        $this->assertSame('', $html);
        $this->assertSame('/erp/login', $redirector->lastLocation());
    }

    public function testWarehousePageShowsListAndCreateFormForLoggedInUser(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => 'Admin']);
        $service = new WarehouseService(new InMemoryWarehouseRepository());
        $service->create([
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
        ]);
        $controller = new WarehouseController($service, $session, new InMemoryRedirector());

        $html = $controller->index();

        $this->assertStringContains('仓库档案', $html);
        $this->assertStringContains('新增仓库', $html);
        $this->assertStringContains('保存仓库', $html);
        $this->assertStringContains('WH-001', $html);
        $this->assertStringContains('name="csrf_token"', $html);
        $this->assertStringContains('action="/erp/warehouses"', $html);
        $this->assertStringNotContains('Warehouse Master', $html);
        $this->assertStringNotContains('Save Warehouse', $html);
    }

    public function testWarehousePageProvidesEditAndStatusActions(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $service = new WarehouseService(new InMemoryWarehouseRepository());
        $service->create([
            'code' => 'WH-001',
            'name' => '主仓库',
        ]);
        $controller = new WarehouseController($service, $session, new InMemoryRedirector());

        $html = $controller->index();

        $this->assertStringContains('编辑', $html);
        $this->assertStringContains('停用', $html);
        $this->assertStringContains('href="/erp/warehouses/edit?id=1"', $html);
        $this->assertStringContains('action="/erp/warehouses/status"', $html);
    }

    public function testWarehouseControllerUpdatesWarehouseAndRedirects(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $service = new WarehouseService(new InMemoryWarehouseRepository());
        $warehouse = $service->create([
            'code' => 'WH-001',
            'name' => '旧仓库',
        ]);
        $redirector = new InMemoryRedirector();
        $controller = new WarehouseController($service, $session, $redirector);

        $controller->update([
            'id' => (string) $warehouse['id'],
            'csrf_token' => $session->csrfToken(),
            'code' => 'WH-002',
            'name' => '新仓库',
        ]);

        $this->assertSame('/erp/warehouses?updated=1', $redirector->lastLocation());
        $this->assertSame('新仓库', $service->list()[0]['name']);
    }

    public function testWarehouseControllerTogglesWarehouseStatusAndRedirects(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $service = new WarehouseService(new InMemoryWarehouseRepository());
        $warehouse = $service->create([
            'code' => 'WH-001',
            'name' => '主仓库',
        ]);
        $redirector = new InMemoryRedirector();
        $controller = new WarehouseController($service, $session, $redirector);

        $controller->status([
            'id' => (string) $warehouse['id'],
            'is_active' => '0',
            'csrf_token' => $session->csrfToken(),
        ]);

        $this->assertSame('/erp/warehouses?status=1', $redirector->lastLocation());
        $this->assertSame(0, $service->list()[0]['is_active']);
    }

    public function testBomServiceCreatesBomWithComponentsAndCalculatesRequirements(): void
    {
        $materials = new InMemoryMaterialRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $component = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $service = new BomService(new InMemoryBomRepository(), $materials);

        $bom = $service->create([
            'parent_material_id' => (string) $parent['id'],
            'version' => 'v1',
            'items' => [
                [
                    'component_material_id' => (string) $component['id'],
                    'quantity' => '2.5',
                    'scrap_rate' => '10',
                ],
            ],
        ]);
        $requirements = $service->requirements($bom['id'], '4');

        $this->assertSame('FG-001', $bom['parent_material_code']);
        $this->assertSame('MAT-001', $bom['items'][0]['component_material_code']);
        $this->assertSame('2.5', $bom['items'][0]['quantity']);
        $this->assertSame('11', $requirements[0]['required_quantity']);
    }

    public function testBomServiceRejectsParentMaterialAsComponent(): void
    {
        $materials = new InMemoryMaterialRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $service = new BomService(new InMemoryBomRepository(), $materials);

        try {
            $service->create([
                'parent_material_id' => (string) $parent['id'],
                'version' => 'v1',
                'items' => [
                    [
                        'component_material_id' => (string) $parent['id'],
                        'quantity' => '1',
                    ],
                ],
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContains('component', $exception->getMessage());
            return;
        }

        throw new \RuntimeException('Expected BOM to reject parent as component');
    }

    public function testBomPageShowsListAndCreateFormForLoggedInUser(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $materials = new InMemoryMaterialRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $component = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $service = new BomService(new InMemoryBomRepository(), $materials);
        $service->create([
            'parent_material_id' => (string) $parent['id'],
            'version' => 'v1',
            'items' => [
                [
                    'component_material_id' => (string) $component['id'],
                    'quantity' => '2',
                ],
            ],
        ]);
        $controller = new BomController(
            $service,
            new MaterialService($materials),
            $session,
            new InMemoryRedirector(),
        );

        $html = $controller->index();

        $this->assertStringContains('BOM 管理', $html);
        $this->assertStringContains('新增 BOM', $html);
        $this->assertStringContains('FG-001', $html);
        $this->assertStringContains('MAT-001', $html);
        $this->assertStringContains('action="/erp/boms"', $html);
        $this->assertStringContains('name="component_material_id"', $html);
    }

    public function testBomControllerStoresBomAndRedirects(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $materials = new InMemoryMaterialRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $component = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $service = new BomService(new InMemoryBomRepository(), $materials);
        $redirector = new InMemoryRedirector();
        $controller = new BomController($service, new MaterialService($materials), $session, $redirector);

        $controller->store([
            'csrf_token' => $session->csrfToken(),
            'parent_material_id' => (string) $parent['id'],
            'version' => 'v1',
            'component_material_id' => (string) $component['id'],
            'quantity' => '3',
            'scrap_rate' => '5',
        ]);

        $this->assertSame('/erp/boms?created=1', $redirector->lastLocation());
        $this->assertSame(1, count($service->list()));
    }

    public function testPurchaseOrderServiceCreatesOrderWithAmount(): void
    {
        $materials = new InMemoryMaterialRepository();
        $material = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $service = new PurchaseOrderService(new InMemoryPurchaseOrderRepository(), $materials);

        $order = $service->create([
            'supplier_name' => '上海供应商',
            'order_no' => 'PO-001',
            'expected_date' => '2026-06-30',
            'material_id' => (string) $material['id'],
            'quantity' => '10',
            'unit_price' => '12.5',
        ]);

        $this->assertSame('PO-001', $order['order_no']);
        $this->assertSame('上海供应商', $order['supplier_name']);
        $this->assertSame('MAT-001', $order['items'][0]['material_code']);
        $this->assertSame('125', $order['total_amount']);
        $this->assertSame('draft', $order['status']);
    }

    public function testPurchaseOrderServiceRejectsInvalidQuantityAndPrice(): void
    {
        $materials = new InMemoryMaterialRepository();
        $material = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $service = new PurchaseOrderService(new InMemoryPurchaseOrderRepository(), $materials);

        try {
            $service->create([
                'supplier_name' => '上海供应商',
                'order_no' => 'PO-001',
                'material_id' => (string) $material['id'],
                'quantity' => '0',
                'unit_price' => '-1',
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContains('quantity', $exception->getMessage());
            return;
        }

        throw new \RuntimeException('Expected invalid purchase quantity to be rejected');
    }

    public function testPurchasePageShowsListAndCreateFormForLoggedInUser(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $materials = new InMemoryMaterialRepository();
        $material = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $service = new PurchaseOrderService(new InMemoryPurchaseOrderRepository(), $materials);
        $service->create([
            'supplier_name' => '上海供应商',
            'order_no' => 'PO-001',
            'material_id' => (string) $material['id'],
            'quantity' => '10',
            'unit_price' => '12.5',
        ]);
        $controller = new PurchaseController(
            $service,
            new MaterialService($materials),
            $session,
            new InMemoryRedirector(),
        );

        $html = $controller->index();

        $this->assertStringContains('采购订单', $html);
        $this->assertStringContains('新增采购单', $html);
        $this->assertStringContains('PO-001', $html);
        $this->assertStringContains('上海供应商', $html);
        $this->assertStringContains('125', $html);
        $this->assertStringContains('action="/erp/purchases"', $html);
        $this->assertStringContains('name="unit_price"', $html);
    }

    public function testPurchaseControllerStoresOrderAndRedirects(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $materials = new InMemoryMaterialRepository();
        $material = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $service = new PurchaseOrderService(new InMemoryPurchaseOrderRepository(), $materials);
        $redirector = new InMemoryRedirector();
        $controller = new PurchaseController($service, new MaterialService($materials), $session, $redirector);

        $controller->store([
            'csrf_token' => $session->csrfToken(),
            'supplier_name' => '上海供应商',
            'order_no' => 'PO-002',
            'expected_date' => '2026-07-01',
            'material_id' => (string) $material['id'],
            'quantity' => '5',
            'unit_price' => '20',
        ]);

        $this->assertSame('/erp/purchases?created=1', $redirector->lastLocation());
        $this->assertSame(1, count($service->list()));
    }

    public function testWorkOrderServiceCreatesOrderFromBomWithRequirements(): void
    {
        $materials = new InMemoryMaterialRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $component = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $bomService = new BomService(new InMemoryBomRepository(), $materials);
        $bom = $bomService->create([
            'parent_material_id' => (string) $parent['id'],
            'version' => 'v1',
            'items' => [
                [
                    'component_material_id' => (string) $component['id'],
                    'quantity' => '2',
                    'scrap_rate' => '5',
                ],
            ],
        ]);
        $service = new WorkOrderService(new InMemoryWorkOrderRepository(), $bomService);

        $order = $service->create([
            'order_no' => 'WO-001',
            'bom_id' => (string) $bom['id'],
            'planned_quantity' => '10',
            'due_date' => '2026-07-15',
        ]);

        $this->assertSame('WO-001', $order['order_no']);
        $this->assertSame('FG-001', $order['parent_material_code']);
        $this->assertSame('10', $order['planned_quantity']);
        $this->assertSame('21', $order['requirements'][0]['required_quantity']);
        $this->assertSame('planned', $order['status']);
    }

    public function testWorkOrderServiceRejectsInvalidBomOrQuantity(): void
    {
        $service = new WorkOrderService(
            new InMemoryWorkOrderRepository(),
            new BomService(new InMemoryBomRepository(), new InMemoryMaterialRepository()),
        );

        try {
            $service->create([
                'order_no' => 'WO-001',
                'bom_id' => '99',
                'planned_quantity' => '0',
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContains('planned quantity', $exception->getMessage());
            return;
        }

        throw new \RuntimeException('Expected invalid work order quantity to be rejected');
    }

    public function testWorkOrderPageShowsListAndCreateFormForLoggedInUser(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $materials = new InMemoryMaterialRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $component = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $bomService = new BomService(new InMemoryBomRepository(), $materials);
        $bom = $bomService->create([
            'parent_material_id' => (string) $parent['id'],
            'version' => 'v1',
            'items' => [
                [
                    'component_material_id' => (string) $component['id'],
                    'quantity' => '2',
                ],
            ],
        ]);
        $workOrders = new WorkOrderService(new InMemoryWorkOrderRepository(), $bomService);
        $workOrders->create([
            'order_no' => 'WO-001',
            'bom_id' => (string) $bom['id'],
            'planned_quantity' => '10',
        ]);
        $warehouses = new InMemoryWarehouseRepository();
        $controller = new WorkOrderController(
            $workOrders,
            $bomService,
            new WarehouseService($warehouses),
            new InventoryService(new InMemoryInventoryTransactionRepository(), $materials, $warehouses),
            $session,
            new InMemoryRedirector(),
        );

        $html = $controller->index();

        $this->assertStringContains('生产工单', $html);
        $this->assertStringContains('新增工单', $html);
        $this->assertStringContains('WO-001', $html);
        $this->assertStringContains('FG-001', $html);
        $this->assertStringContains('MAT-001', $html);
        $this->assertStringContains('action="/erp/work-orders"', $html);
        $this->assertStringContains('action="/erp/work-orders/complete"', $html);
        $this->assertStringContains('name="planned_quantity"', $html);
    }

    public function testWorkOrderControllerStoresOrderAndRedirects(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $materials = new InMemoryMaterialRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $component = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $bomService = new BomService(new InMemoryBomRepository(), $materials);
        $bom = $bomService->create([
            'parent_material_id' => (string) $parent['id'],
            'version' => 'v1',
            'items' => [
                [
                    'component_material_id' => (string) $component['id'],
                    'quantity' => '2',
                ],
            ],
        ]);
        $workOrders = new WorkOrderService(new InMemoryWorkOrderRepository(), $bomService);
        $redirector = new InMemoryRedirector();
        $warehouses = new InMemoryWarehouseRepository();
        $controller = new WorkOrderController(
            $workOrders,
            $bomService,
            new WarehouseService($warehouses),
            new InventoryService(new InMemoryInventoryTransactionRepository(), $materials, $warehouses),
            $session,
            $redirector,
        );

        $controller->store([
            'csrf_token' => $session->csrfToken(),
            'order_no' => 'WO-002',
            'bom_id' => (string) $bom['id'],
            'planned_quantity' => '5',
            'due_date' => '2026-07-20',
        ]);

        $this->assertSame('/erp/work-orders?created=1', $redirector->lastLocation());
        $this->assertSame(1, count($workOrders->list()));
    }

    public function testWorkOrderServiceIssuesMaterialsToInventory(): void
    {
        $materials = new InMemoryMaterialRepository();
        $warehouses = new InMemoryWarehouseRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $component = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $warehouse = $warehouses->create([
            'code' => 'WH-001',
            'name' => '原料仓',
        ]);
        $bomService = new BomService(new InMemoryBomRepository(), $materials);
        $bom = $bomService->create([
            'parent_material_id' => (string) $parent['id'],
            'version' => 'v1',
            'items' => [
                [
                    'component_material_id' => (string) $component['id'],
                    'quantity' => '2',
                    'scrap_rate' => '5',
                ],
            ],
        ]);
        $workOrders = new WorkOrderService(new InMemoryWorkOrderRepository(), $bomService);
        $order = $workOrders->create([
            'order_no' => 'WO-001',
            'bom_id' => (string) $bom['id'],
            'planned_quantity' => '10',
        ]);
        $inventory = new InventoryService(new InMemoryInventoryTransactionRepository(), $materials, $warehouses);

        $transactions = $workOrders->issueMaterials($order['id'], $warehouse['id'], $inventory);

        $this->assertSame(1, count($transactions));
        $this->assertSame('outbound', $transactions[0]['transaction_type']);
        $this->assertSame('21', $transactions[0]['quantity']);
        $this->assertSame('WO-001', $transactions[0]['reference_no']);
        $this->assertSame('-21', $inventory->stockBalance($component['id'], $warehouse['id']));
        $this->assertSame('issued', $workOrders->list()[0]['status']);
    }

    public function testWorkOrderControllerIssuesMaterialsAndRedirects(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $materials = new InMemoryMaterialRepository();
        $warehouses = new InMemoryWarehouseRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $component = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $warehouse = $warehouses->create([
            'code' => 'WH-001',
            'name' => '原料仓',
        ]);
        $bomService = new BomService(new InMemoryBomRepository(), $materials);
        $bom = $bomService->create([
            'parent_material_id' => (string) $parent['id'],
            'version' => 'v1',
            'items' => [
                [
                    'component_material_id' => (string) $component['id'],
                    'quantity' => '2',
                ],
            ],
        ]);
        $workOrders = new WorkOrderService(new InMemoryWorkOrderRepository(), $bomService);
        $order = $workOrders->create([
            'order_no' => 'WO-001',
            'bom_id' => (string) $bom['id'],
            'planned_quantity' => '5',
        ]);
        $inventory = new InventoryService(new InMemoryInventoryTransactionRepository(), $materials, $warehouses);
        $redirector = new InMemoryRedirector();
        $controller = new WorkOrderController(
            $workOrders,
            $bomService,
            new WarehouseService($warehouses),
            $inventory,
            $session,
            $redirector,
        );

        $controller->issue([
            'csrf_token' => $session->csrfToken(),
            'id' => (string) $order['id'],
            'warehouse_id' => (string) $warehouse['id'],
        ]);

        $this->assertSame('/erp/work-orders?issued=1', $redirector->lastLocation());
        $this->assertSame('-10', $inventory->stockBalance($component['id'], $warehouse['id']));
        $this->assertSame('issued', $workOrders->list()[0]['status']);
    }

    public function testWorkOrderServiceCompletesOrderAndReceivesFinishedGoods(): void
    {
        $materials = new InMemoryMaterialRepository();
        $warehouses = new InMemoryWarehouseRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $component = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $warehouse = $warehouses->create([
            'code' => 'WH-FG',
            'name' => '成品仓',
        ]);
        $bomService = new BomService(new InMemoryBomRepository(), $materials);
        $bom = $bomService->create([
            'parent_material_id' => (string) $parent['id'],
            'version' => 'v1',
            'items' => [
                [
                    'component_material_id' => (string) $component['id'],
                    'quantity' => '2',
                ],
            ],
        ]);
        $workOrders = new WorkOrderService(new InMemoryWorkOrderRepository(), $bomService);
        $order = $workOrders->create([
            'order_no' => 'WO-003',
            'bom_id' => (string) $bom['id'],
            'planned_quantity' => '10',
        ]);
        $inventory = new InventoryService(new InMemoryInventoryTransactionRepository(), $materials, $warehouses);

        $transaction = $workOrders->complete($order['id'], $warehouse['id'], $inventory);

        $this->assertSame('inbound', $transaction['transaction_type']);
        $this->assertSame((int) $parent['id'], $transaction['material_id']);
        $this->assertSame('10', $transaction['quantity']);
        $this->assertSame('WO-003', $transaction['reference_no']);
        $this->assertSame('10', $inventory->stockBalance($parent['id'], $warehouse['id']));
        $this->assertSame('completed', $workOrders->list()[0]['status']);
    }

    public function testWorkOrderControllerCompletesOrderAndRedirects(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => '管理员']);
        $materials = new InMemoryMaterialRepository();
        $warehouses = new InMemoryWarehouseRepository();
        $parent = $materials->create([
            'code' => 'FG-001',
            'name' => '成品A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'manufactured',
        ]);
        $component = $materials->create([
            'code' => 'MAT-001',
            'name' => '原料A',
            'specification' => '',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $warehouse = $warehouses->create([
            'code' => 'WH-FG',
            'name' => '成品仓',
        ]);
        $bomService = new BomService(new InMemoryBomRepository(), $materials);
        $bom = $bomService->create([
            'parent_material_id' => (string) $parent['id'],
            'version' => 'v1',
            'items' => [
                [
                    'component_material_id' => (string) $component['id'],
                    'quantity' => '2',
                ],
            ],
        ]);
        $workOrders = new WorkOrderService(new InMemoryWorkOrderRepository(), $bomService);
        $order = $workOrders->create([
            'order_no' => 'WO-004',
            'bom_id' => (string) $bom['id'],
            'planned_quantity' => '5',
        ]);
        $inventory = new InventoryService(new InMemoryInventoryTransactionRepository(), $materials, $warehouses);
        $redirector = new InMemoryRedirector();
        $controller = new WorkOrderController(
            $workOrders,
            $bomService,
            new WarehouseService($warehouses),
            $inventory,
            $session,
            $redirector,
        );

        $controller->complete([
            'csrf_token' => $session->csrfToken(),
            'id' => (string) $order['id'],
            'warehouse_id' => (string) $warehouse['id'],
        ]);

        $this->assertSame('/erp/work-orders?completed=1', $redirector->lastLocation());
        $this->assertSame('5', $inventory->stockBalance($parent['id'], $warehouse['id']));
        $this->assertSame('completed', $workOrders->list()[0]['status']);
    }

    public function testInventoryServiceRecordsTransactionsAndCalculatesStockBalance(): void
    {
        $materials = new InMemoryMaterialRepository();
        $warehouses = new InMemoryWarehouseRepository();
        $material = $materials->create([
            'code' => 'MAT-001',
            'name' => 'Steel Screw',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $warehouse = $warehouses->create([
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
        ]);
        $service = new InventoryService(
            new InMemoryInventoryTransactionRepository(),
            $materials,
            $warehouses,
        );

        $service->record([
            'material_id' => (string) $material['id'],
            'warehouse_id' => (string) $warehouse['id'],
            'transaction_type' => 'inbound',
            'quantity' => '10',
            'reference_no' => 'PO-001',
        ]);
        $service->record([
            'material_id' => (string) $material['id'],
            'warehouse_id' => (string) $warehouse['id'],
            'transaction_type' => 'outbound',
            'quantity' => '3',
            'reference_no' => 'WO-001',
        ]);
        $service->record([
            'material_id' => (string) $material['id'],
            'warehouse_id' => (string) $warehouse['id'],
            'transaction_type' => 'adjustment',
            'quantity' => '1.5',
            'reference_no' => 'ADJ-001',
        ]);

        $this->assertSame(3, count($service->list()));
        $this->assertSame('8.5', $service->stockBalance($material['id'], $warehouse['id']));
        $balances = $service->stockBalances();
        $this->assertSame(1, count($balances));
        $this->assertSame('MAT-001', $balances[0]['material_code']);
        $this->assertSame('WH-001', $balances[0]['warehouse_code']);
        $this->assertSame('8.5', $balances[0]['quantity']);
    }

    public function testInventoryServiceRejectsInvalidQuantity(): void
    {
        $materials = new InMemoryMaterialRepository();
        $warehouses = new InMemoryWarehouseRepository();
        $material = $materials->create([
            'code' => 'MAT-001',
            'name' => 'Steel Screw',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $warehouse = $warehouses->create([
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
        ]);
        $service = new InventoryService(
            new InMemoryInventoryTransactionRepository(),
            $materials,
            $warehouses,
        );

        try {
            $service->record([
                'material_id' => (string) $material['id'],
                'warehouse_id' => (string) $warehouse['id'],
                'transaction_type' => 'inbound',
                'quantity' => '0',
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContains('quantity', $exception->getMessage());
            return;
        }

        throw new \RuntimeException('Expected invalid inventory quantity to be rejected');
    }

    public function testInventoryPageRedirectsGuestToLogin(): void
    {
        App::setBasePath('/erp');
        $redirector = new InMemoryRedirector();
        $controller = new InventoryController(
            new InventoryService(
                new InMemoryInventoryTransactionRepository(),
                new InMemoryMaterialRepository(),
                new InMemoryWarehouseRepository(),
            ),
            new MaterialService(new InMemoryMaterialRepository()),
            new WarehouseService(new InMemoryWarehouseRepository()),
            new InMemorySessionStore(),
            $redirector,
        );

        $html = $controller->index();

        $this->assertSame('', $html);
        $this->assertSame('/erp/login', $redirector->lastLocation());
    }

    public function testInventoryPageShowsFormAndTransactionsForLoggedInUser(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => 'Admin']);
        $materials = new InMemoryMaterialRepository();
        $warehouses = new InMemoryWarehouseRepository();
        $material = $materials->create([
            'code' => 'MAT-001',
            'name' => 'Steel Screw',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $warehouse = $warehouses->create([
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
        ]);
        $inventory = new InventoryService(
            new InMemoryInventoryTransactionRepository(),
            $materials,
            $warehouses,
        );
        $inventory->record([
            'material_id' => (string) $material['id'],
            'warehouse_id' => (string) $warehouse['id'],
            'transaction_type' => 'inbound',
            'quantity' => '10',
            'reference_no' => 'PO-001',
        ]);
        $controller = new InventoryController(
            $inventory,
            new MaterialService($materials),
            new WarehouseService($warehouses),
            $session,
            new InMemoryRedirector(),
        );

        $html = $controller->index();

        $this->assertStringContains('库存流水', $html);
        $this->assertStringContains('新增库存流水', $html);
        $this->assertStringContains('MAT-001', $html);
        $this->assertStringContains('WH-001', $html);
        $this->assertStringContains('PO-001', $html);
        $this->assertStringContains('action="/erp/inventory"', $html);
    }

    public function testInventoryBalancePageShowsCurrentStockForLoggedInUser(): void
    {
        App::setBasePath('/erp');
        $session = new InMemorySessionStore();
        $session->setUser(['id' => 1, 'email' => 'admin@goenn.online', 'name' => 'Admin']);
        $materials = new InMemoryMaterialRepository();
        $warehouses = new InMemoryWarehouseRepository();
        $material = $materials->create([
            'code' => 'MAT-001',
            'name' => 'Steel Screw',
            'specification' => 'M6x20',
            'base_unit' => 'pcs',
            'material_type' => 'purchased',
        ]);
        $warehouse = $warehouses->create([
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
        ]);
        $inventory = new InventoryService(
            new InMemoryInventoryTransactionRepository(),
            $materials,
            $warehouses,
        );
        $inventory->record([
            'material_id' => (string) $material['id'],
            'warehouse_id' => (string) $warehouse['id'],
            'transaction_type' => 'inbound',
            'quantity' => '10',
        ]);
        $inventory->record([
            'material_id' => (string) $material['id'],
            'warehouse_id' => (string) $warehouse['id'],
            'transaction_type' => 'outbound',
            'quantity' => '4',
        ]);
        $controller = new InventoryController(
            $inventory,
            new MaterialService($materials),
            new WarehouseService($warehouses),
            $session,
            new InMemoryRedirector(),
        );

        $html = $controller->balances();

        $this->assertStringContains('库存余额', $html);
        $this->assertStringContains('MAT-001', $html);
        $this->assertStringContains('WH-001', $html);
        $this->assertStringContains('6', $html);
    }

    public function testSharedHostBuildCreatesSafeDeployLayout(): void
    {
        $root = dirname(__DIR__, 2);
        $output = $root . '/storage/cache/test-shared-host-build';
        $this->removeDirectory($output);

        $php = PHP_BINARY;
        $command = escapeshellarg($php) . ' '
            . escapeshellarg($root . '/scripts/build_shared_host.php') . ' --output='
            . escapeshellarg($output);
        exec($command, $lines, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $lines));
        $this->assertTrue(is_file($output . '/index.php'), 'Expected public index in deploy root');
        $this->assertTrue(is_file($output . '/.htaccess'), 'Expected root htaccess in deploy root');
        $this->assertTrue(is_dir($output . '/assets'), 'Expected assets in deploy root');
        $this->assertTrue(is_dir($output . '/_app/bootstrap'), 'Expected bootstrap in internal app directory');
        $this->assertTrue(is_dir($output . '/_app/src'), 'Expected source in internal app directory');
        $this->assertStringContains('_app/bootstrap/web.php', file_get_contents($output . '/index.php') ?: '');
        $this->assertStringContains('Require all denied', file_get_contents($output . '/_app/.htaccess') ?: '');

        $this->removeDirectory($output);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = array_diff(scandir($directory) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
