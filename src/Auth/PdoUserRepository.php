<?php

declare(strict_types=1);

namespace Erp\Auth;

use PDO;

final class PdoUserRepository implements UserRepository
{
    private const DEFAULT_ROLES = [
        'admin' => '管理员',
        'planner' => '计划员',
        'warehouse' => '仓库员',
        'purchasing' => '采购员',
    ];

    public function __construct(private readonly PDO $pdo)
    {
        $this->ensureDefaultRoles();
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT users.id, users.email, users.name, users.password_hash, users.is_active,
                    COALESCE(roles.code, :fallback_code) AS role_code,
                    COALESCE(roles.name, :fallback_name) AS role_name
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             WHERE users.email = :email
             LIMIT 1'
        );
        $statement->execute([
            'email' => strtolower(trim($email)),
            'fallback_code' => 'admin',
            'fallback_name' => self::DEFAULT_ROLES['admin'],
        ]);
        $user = $statement->fetch();

        return is_array($user) ? [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'name' => (string) $user['name'],
            'password_hash' => (string) $user['password_hash'],
            'is_active' => (int) $user['is_active'],
            'role_code' => (string) $user['role_code'],
            'role_name' => (string) $user['role_name'],
        ] : null;
    }

    public function createAdmin(string $email, string $passwordHash, string $name = '管理员'): void
    {
        $roleId = $this->roleId('admin');
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

    public function listUsers(): array
    {
        $statement = $this->pdo->query(
            'SELECT users.id, users.email, users.name, users.is_active,
                    COALESCE(roles.code, "admin") AS role_code,
                    COALESCE(roles.name, "管理员") AS role_name
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             ORDER BY users.id DESC
             LIMIT 200'
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'name' => (string) $row['name'],
            'is_active' => (int) $row['is_active'],
            'role_code' => (string) $row['role_code'],
            'role_name' => (string) $row['role_name'],
        ], $statement->fetchAll());
    }

    public function listRoles(): array
    {
        $this->ensureDefaultRoles();
        $statement = $this->pdo->query('SELECT code, name FROM roles ORDER BY id ASC');

        return array_map(static fn (array $row): array => [
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
        ], $statement->fetchAll());
    }

    public function createUser(array $data): array
    {
        $roleId = $this->roleId($data['role_code']);
        $statement = $this->pdo->prepare(
            'INSERT INTO users (role_id, email, name, password_hash, is_active, created_at)
             VALUES (:role_id, :email, :name, :password_hash, 1, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'role_id' => $roleId,
            'email' => strtolower(trim($data['email'])),
            'name' => $data['name'],
            'password_hash' => $data['password_hash'],
        ]);

        return $this->userById((int) $this->pdo->lastInsertId());
    }

    public function setActive(int $id, bool $active): array
    {
        $statement = $this->pdo->prepare('UPDATE users SET is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'is_active' => $active ? 1 : 0,
        ]);

        return $this->userById($id);
    }

    private function ensureDefaultRoles(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO roles (code, name, created_at)
             VALUES (:code, :name, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE name = VALUES(name)'
        );

        foreach (self::DEFAULT_ROLES as $code => $name) {
            $statement->execute(['code' => $code, 'name' => $name]);
        }
    }

    private function roleId(string $code): int
    {
        $this->ensureDefaultRoles();
        $statement = $this->pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
        $statement->execute(['code' => $code]);
        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new \InvalidArgumentException('role must exist');
        }

        return (int) $id;
    }

    /**
     * @return array{id:int,email:string,name:string,is_active:int,role_code:string,role_name:string}
     */
    private function userById(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT users.id, users.email, users.name, users.is_active,
                    COALESCE(roles.code, "admin") AS role_code,
                    COALESCE(roles.name, "管理员") AS role_name
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new \RuntimeException('user not found');
        }

        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'name' => (string) $row['name'],
            'is_active' => (int) $row['is_active'],
            'role_code' => (string) $row['role_code'],
            'role_name' => (string) $row['role_name'],
        ];
    }
}
