<?php

declare(strict_types=1);

use Erp\Controller\AuthController;
use Erp\Controller\DashboardController;
use Erp\Auth\AuthService;
use Erp\Auth\NativeSessionStore;
use Erp\Auth\PdoUserRepository;
use Erp\Core\App;
use Erp\Core\Autoloader;
use Erp\Core\Router;
use Erp\Database\Database;
use Erp\Http\NativeRedirector;

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
$databaseConfig = dirname(__DIR__) . '/config/database.php';
if (is_file($databaseConfig)) {
    $authService = new AuthService(new PdoUserRepository(Database::fromConfigFile($databaseConfig)));
}
$auth = new AuthController($authService, $session, $redirector);

$router->get('/', [$dashboard, 'index']);
$router->get('/login', [$auth, 'login']);
$router->post('/login', [$auth, 'submit']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/health', [$dashboard, 'health']);

return $router;
