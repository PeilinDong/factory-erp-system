<?php

declare(strict_types=1);

namespace Erp\Cli;

final class MigrateCommand
{
    public function __construct(private readonly string $migrationPath)
    {
    }

    /**
     * @param array<int, string> $args
     */
    public function handle(array $args): string
    {
        $dryRun = in_array('--dry-run', $args, true);
        $files = glob($this->migrationPath . '/*.php') ?: [];
        sort($files);

        $output = $dryRun ? "Migration dry run\n" : "Migration execution requires database configuration\n";
        foreach ($files as $file) {
            $migration = require $file;
            $output .= basename($file) . "\n";
            foreach ($migration['tables'] ?? [] as $table) {
                $output .= " - {$table}\n";
            }
        }

        return $output;
    }
}

