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
