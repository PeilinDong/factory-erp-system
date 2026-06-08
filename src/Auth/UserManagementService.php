<?php

declare(strict_types=1);

namespace Erp\Auth;

final class UserManagementService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @return array<int, array{id:int,email:string,name:string,is_active:int,role_code:string,role_name:string}>
     */
    public function list(): array
    {
        return $this->users->listUsers();
    }

    /**
     * @return array<int, array{code:string,name:string}>
     */
    public function roles(): array
    {
        return $this->users->listRoles();
    }

    /**
     * @param array<string, string> $data
     * @return array{id:int,email:string,name:string,is_active:int,role_code:string,role_name:string}
     */
    public function create(array $data): array
    {
        $email = strtolower(trim($data['email'] ?? ''));
        $name = trim($data['name'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $roleCode = trim($data['role_code'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('email is invalid');
        }

        if ($name === '') {
            throw new \InvalidArgumentException('name is required');
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('password must contain at least 8 characters');
        }

        $roleCodes = array_column($this->roles(), 'code');
        if (!in_array($roleCode, $roleCodes, true)) {
            throw new \InvalidArgumentException('role must exist');
        }

        return $this->users->createUser([
            'email' => $email,
            'name' => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_code' => $roleCode,
        ]);
    }

    /**
     * @return array{id:int,email:string,name:string,is_active:int,role_code:string,role_name:string}
     */
    public function setActive(int $id, bool $active): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('user must exist');
        }

        return $this->users->setActive($id, $active);
    }
}
