<?php

declare(strict_types=1);

namespace Erp\Auth;

final class InMemoryUserRepository implements UserRepository
{
    /**
     * @var array<string, array{id:int,code:string,name:string}>
     */
    private array $roles = [
        'admin' => ['id' => 1, 'code' => 'admin', 'name' => '管理员'],
        'general_manager' => ['id' => 2, 'code' => 'general_manager', 'name' => '总经理'],
        'supervisor' => ['id' => 3, 'code' => 'supervisor', 'name' => '主管'],
        'planner' => ['id' => 4, 'code' => 'planner', 'name' => '计划员'],
        'warehouse' => ['id' => 5, 'code' => 'warehouse', 'name' => '仓库员'],
        'purchasing' => ['id' => 6, 'code' => 'purchasing', 'name' => '采购员'],
    ];

    /**
     * @var array<string, array{id:int,email:string,name:string,password_hash:string,is_active:int,role_code:string}>
     */
    private array $users = [];

    /**
     * @param array<int, array{id:int,email:string,name:string,password_hash:string,is_active:int,role_code?:string}> $users
     */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
            $user['role_code'] ??= 'admin';
            $this->users[strtolower($user['email'])] = $user;
        }
    }

    public function findByEmail(string $email): ?array
    {
        return $this->users[strtolower(trim($email))] ?? null;
    }

    public function createAdmin(string $email, string $passwordHash, string $name = '管理员'): void
    {
        $id = count($this->users) + 1;
        $this->users[strtolower($email)] = [
            'id' => $id,
            'email' => strtolower($email),
            'name' => $name,
            'password_hash' => $passwordHash,
            'is_active' => 1,
            'role_code' => 'admin',
        ];
    }

    public function listUsers(): array
    {
        return array_values(array_map($this->withoutPassword(...), $this->users));
    }

    public function listRoles(): array
    {
        return array_values(array_map(
            static fn (array $role): array => ['code' => $role['code'], 'name' => $role['name']],
            $this->roles,
        ));
    }

    public function createUser(array $data): array
    {
        $roleCode = $data['role_code'];
        if (!isset($this->roles[$roleCode])) {
            throw new \InvalidArgumentException('role must exist');
        }

        $id = count($this->users) + 1;
        $user = [
            'id' => $id,
            'email' => strtolower($data['email']),
            'name' => $data['name'],
            'password_hash' => $data['password_hash'],
            'is_active' => 1,
            'role_code' => $roleCode,
        ];
        $this->users[$user['email']] = $user;

        return $this->withoutPassword($user);
    }

    public function setActive(int $id, bool $active): array
    {
        foreach ($this->users as $email => $user) {
            if ((int) $user['id'] !== $id) {
                continue;
            }

            $user['is_active'] = $active ? 1 : 0;
            $this->users[$email] = $user;

            return $this->withoutPassword($user);
        }

        throw new \RuntimeException('user not found');
    }

    /**
     * @param array{id:int,email:string,name:string,password_hash:string,is_active:int,role_code?:string} $user
     * @return array{id:int,email:string,name:string,is_active:int,role_code:string,role_name:string}
     */
    private function withoutPassword(array $user): array
    {
        $roleCode = (string) ($user['role_code'] ?? 'admin');
        return [
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'name' => (string) $user['name'],
            'is_active' => (int) $user['is_active'],
            'role_code' => $roleCode,
            'role_name' => $this->roles[$roleCode]['name'] ?? $roleCode,
        ];
    }
}
