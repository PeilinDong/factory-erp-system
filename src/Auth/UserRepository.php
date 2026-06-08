<?php

declare(strict_types=1);

namespace Erp\Auth;

interface UserRepository
{
    /**
     * @return null|array{id:int,email:string,name:string,password_hash:string,is_active:int,role_code?:string,role_name?:string}
     */
    public function findByEmail(string $email): ?array;

    public function createAdmin(string $email, string $passwordHash, string $name = '管理员'): void;

    /**
     * @return array<int, array{id:int,email:string,name:string,is_active:int,role_code:string,role_name:string}>
     */
    public function listUsers(): array;

    /**
     * @return array<int, array{code:string,name:string}>
     */
    public function listRoles(): array;

    /**
     * @param array{email:string,name:string,password_hash:string,role_code:string} $data
     * @return array{id:int,email:string,name:string,is_active:int,role_code:string,role_name:string}
     */
    public function createUser(array $data): array;

    /**
     * @return array{id:int,email:string,name:string,is_active:int,role_code:string,role_name:string}
     */
    public function setActive(int $id, bool $active): array;
}
