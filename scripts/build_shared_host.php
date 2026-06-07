<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$output = option($argv, '--output') ?? ($root . '/build/shared-host');

removeDirectory($output);
mkdir($output, 0777, true);

copyDirectory($root . '/public/assets', $output . '/assets');
copyDirectory($root . '/bootstrap', $output . '/_app/bootstrap');
copyDirectory($root . '/src', $output . '/_app/src');
copyDirectory($root . '/config', $output . '/_app/config');
copyDirectory($root . '/database', $output . '/_app/database');
copyDirectory($root . '/bin', $output . '/_app/bin');

mkdir($output . '/_app/storage/cache', 0777, true);
mkdir($output . '/_app/storage/logs', 0777, true);

file_put_contents($output . '/index.php', <<<'PHP'
<?php

declare(strict_types=1);

$router = require __DIR__ . '/_app/bootstrap/web.php';

$basePath = \Erp\Core\App::basePath();
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath)) ?: '/';
}

echo $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);

PHP);

file_put_contents($output . '/.htaccess', <<<'HTACCESS'
DirectoryIndex index.php

RewriteEngine On
RewriteBase /erp/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]

HTACCESS);

file_put_contents($output . '/_app/.htaccess', <<<'HTACCESS'
Require all denied

HTACCESS);

echo "Built shared-host package at {$output}\n";

/**
 * @param array<int, string> $argv
 */
function option(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return null;
}

function copyDirectory(string $source, string $target): void
{
    if (!is_dir($source)) {
        return;
    }

    mkdir($target, 0777, true);
    $items = array_diff(scandir($source) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
        $targetPath = $target . DIRECTORY_SEPARATOR . $item;
        if (is_dir($sourcePath)) {
            copyDirectory($sourcePath, $targetPath);
        } else {
            copy($sourcePath, $targetPath);
        }
    }
}

function removeDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = array_diff(scandir($directory) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $path = $directory . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? removeDirectory($path) : unlink($path);
    }
    rmdir($directory);
}
