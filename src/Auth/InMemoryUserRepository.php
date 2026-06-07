<?php

declare(strict_types=1);

namespace Erp\Auth;

final class InMemoryUserRepository implements UserRepository
{
    /**
     * @var array<string, array{id:int,email:string,name:string,password_hash:string,is_active:int}>
     */
    private array $users = [];

    /**
     * @param array<int, array{id:int,email:string,name:string,password_hash:string,is_active:int}> $users
     */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
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
            'email' => $email,
            'name' => $name,
            'password_hash' => $passwordHash,
            'is_active' => 1,
        ];
    }
}

