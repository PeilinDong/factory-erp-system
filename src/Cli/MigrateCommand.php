<?php

declare(strict_types=1);

namespace Erp\Cli;

final class MigrateCommand
{
    /**
     * @var null|callable(): \PDO
     */
    private $pdoFactory;

    /**
     * @param callable(): \PDO|null $pdoFactory
     */
    public function __construct(
        private readonly string $migrationPath,
        ?callable $pdoFactory = null,
    )
    {
        $this->pdoFactory = $pdoFactory;
    }

    /**
     * @param array<int, string> $args
     */
    public function handle(array $args): string
    {
        $dryRun = in_array('--dry-run', $args, true);
        $files = glob($this->migrationPath . '/*.php') ?: [];
        sort($files);

        $output = $dryRun ? "Migration dry run\n" : "Migration execution\n";
        $pdo = null;
        if (!$dryRun) {
            if (!is_callable($this->pdoFactory)) {
                return "Migration execution requires database configuration\n";
            }
            $pdo = call_user_func($this->pdoFactory);
        }

        foreach ($files as $file) {
            $migration = require $file;
            $output .= basename($file) . "\n";
            foreach ($migration['tables'] ?? [] as $table) {
                $output .= " - {$table}\n";
            }
            if ($pdo !== null) {
                foreach ($migration['sql'] ?? [] as $sql) {
                    $pdo->exec($sql);
                }
            }
        }

        return $output;
    }
}
