<?php

declare(strict_types=1);

namespace Erp\Core;

final class Autoloader
{
    public static function register(string $sourceRoot): void
    {
        spl_autoload_register(static function (string $class) use ($sourceRoot): void {
            $prefix = 'Erp\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $path = rtrim($sourceRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($path)) {
                require $path;
            }
        });
    }
}

