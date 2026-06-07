<?php

declare(strict_types=1);

namespace Erp\Cli;

final class Application
{
    /**
     * @param array<string, callable(array<int, string>): string> $commands
     */
    public function __construct(private readonly array $commands)
    {
    }

    public static function default(): self
    {
        return new self([
            'health' => [new HealthCommand(), 'handle'],
            'migrate' => [new MigrateCommand(dirname(__DIR__, 2) . '/database/migrations'), 'handle'],
            'create-admin' => [new CreateAdminCommand(), 'handle'],
        ]);
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv): string
    {
        $command = $argv[1] ?? 'help';
        if ($command === 'help' || !isset($this->commands[$command])) {
            return $this->help();
        }

        return (string) call_user_func($this->commands[$command], array_slice($argv, 2));
    }

    private function help(): string
    {
        return "Factory ERP CLI\nCommands: health, migrate, create-admin\n";
    }
}

