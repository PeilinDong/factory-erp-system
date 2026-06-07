<?php

declare(strict_types=1);

namespace Erp\Cli;

final class HealthCommand
{
    /**
     * @param array<int, string> $args
     */
    public function handle(array $args): string
    {
        return sprintf(
            "Factory ERP health check\nPHP: %s\nStatus: OK\n",
            PHP_VERSION
        );
    }
}

