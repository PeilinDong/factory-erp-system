<?php

declare(strict_types=1);

namespace Erp\Core;

final class App
{
    private static string $basePath = '/erp';

    public static function setBasePath(string $basePath): void
    {
        $basePath = '/' . trim($basePath, '/');
        self::$basePath = $basePath === '/' ? '' : $basePath;
    }

    public static function basePath(): string
    {
        return self::$basePath;
    }

    public static function url(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return self::$basePath . $path;
    }

    public static function asset(string $path): string
    {
        return self::url($path);
    }
}

