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
