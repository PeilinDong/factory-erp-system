<?php

declare(strict_types=1);

namespace Erp\Database;

use PDO;

final class Database
{
    public static function fromConfigFile(string $path): PDO
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Database config not found: {$path}");
        }

        $config = require $path;
        $charset = $config['charset'] ?? 'utf8mb4';
        $port = (int) ($config['port'] ?? 3306);
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $port,
            $config['database'],
            $charset
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}

