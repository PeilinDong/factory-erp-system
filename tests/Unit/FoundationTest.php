<?php

declare(strict_types=1);

namespace Tests\Unit;

use Erp\Cli\Application;
use Erp\Core\Config;
use Erp\Core\Router;
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

        $this->assertSame('ok', $router->dispatch('GET', '/health'));
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
}
