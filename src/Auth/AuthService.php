<?php

declare(strict_types=1);

namespace Erp\Auth;

final class AuthService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @return null|array{id:int,email:string,name:string,role_code:string,role_name:string}
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || (int) $user['is_active'] !== 1) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role_code' => (string) ($user['role_code'] ?? 'admin'),
            'role_name' => (string) ($user['role_name'] ?? '管理员'),
        ];
    }
}
