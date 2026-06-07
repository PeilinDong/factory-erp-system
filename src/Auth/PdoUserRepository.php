<?php

declare(strict_types=1);

namespace Erp\Auth;

use PDO;

final class PdoUserRepository implements UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, email, name, password_hash, is_active FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        return is_array($user) ? [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'name' => (string) $user['name'],
            'password_hash' => (string) $user['password_hash'],
            'is_active' => (int) $user['is_active'],
        ] : null;
    }

    public function createAdmin(string $email, string $passwordHash, string $name = '管理员'): void
    {
        $roleId = $this->ensureAdminRole();
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role_id, email, name, password_hash, is_active, created_at)
             VALUES (:role_id, :email, :name, :password_hash, 1, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                role_id = VALUES(role_id),
                name = VALUES(name),
                password_hash = VALUES(password_hash),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'role_id' => $roleId,
            'email' => strtolower(trim($email)),
            'name' => $name,
            'password_hash' => $passwordHash,
        ]);
    }

    private function ensureAdminRole(): int
    {
        $this->pdo->exec(
            "INSERT INTO roles (code, name, created_at)
             VALUES ('admin', '系统管理员', CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE name = VALUES(name)"
        );

        $id = $this->pdo->query("SELECT id FROM roles WHERE code = 'admin' LIMIT 1")->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException('Unable to create admin role');
        }

        return (int) $id;
    }
}

