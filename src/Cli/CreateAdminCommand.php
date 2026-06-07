<?php

declare(strict_types=1);

namespace Erp\Cli;

final class CreateAdminCommand
{
    /**
     * @var null|callable(): \Erp\Auth\UserRepository
     */
    private $repositoryFactory;

    /**
     * @param null|callable(): \Erp\Auth\UserRepository $repositoryFactory
     */
    public function __construct(?callable $repositoryFactory = null)
    {
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @param array<int, string> $args
     */
    public function handle(array $args): string
    {
        $email = $this->option($args, '--email') ?? '';
        $password = $this->option($args, '--password') ?? '';
        $dryRun = in_array('--dry-run', $args, true);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid admin email\n";
        }

        if (strlen($password) < 12) {
            return "Admin password must be at least 12 characters\n";
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($dryRun) {
            return "Admin account validated for {$email}\nPassword hash algorithm: " . password_get_info($hash)['algoName'] . "\n";
        }

        if (!is_callable($this->repositoryFactory)) {
            return "Database-backed admin creation is not configured in this command yet\n";
        }

        $repository = call_user_func($this->repositoryFactory);
        $repository->createAdmin($email, $hash);

        return "Admin account created or updated for {$email}\n";
    }

    /**
     * @param array<int, string> $args
     */
    private function option(array $args, string $name): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, $name . '=')) {
                return substr($arg, strlen($name) + 1);
            }
        }

        return null;
    }
}
